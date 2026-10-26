<?php

namespace Omnifund\OpenCollective;

use Omnifund\Capabilities;
use Omnifund\Exception\ProviderException;
use Omnifund\Http\Json;
use Omnifund\Model\Income;
use Omnifund\Model\Money;
use Omnifund\Model\Period;
use Omnifund\Model\Profile;
use Omnifund\Model\Sponsor;
use Omnifund\Model\Tier;
use Omnifund\ProfileInterface;
use Omnifund\SponsorsInterface;
use Omnifund\TiersInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A collective through Open Collective's GraphQL API v2: its profile (the
 * backers counted, the yearly budget Open Collective estimates as its
 * income), its tiers, its backers - public data, which the API gives
 * without a key.
 */
final class OpenCollectiveProvider implements ProfileInterface, SponsorsInterface, TiersInterface
{
    public const ENDPOINT = 'https://api.opencollective.com/graphql/v2';
    private const PAGE = 100;

    private const ACCOUNT = 'query($slug: String!) { account(slug: $slug) { slug name description website imageUrl type ... on AccountWithContributions { tiers { nodes { id slug name description amountType frequency amount { valueInCents currency } minimumAmount { valueInCents currency } } } } stats { yearlyBudget { valueInCents currency } } members(role: [BACKER], limit: 1) { totalCount } } }';
    private const MEMBERS = 'query($slug: String!, $limit: Int!, $offset: Int!) { account(slug: $slug) { members(role: [BACKER], limit: $limit, offset: $offset) { totalCount nodes { since tier { name } totalDonations { valueInCents currency } account { slug name imageUrl type website } } } } }';

    /** @var array<string, array<mixed>> */
    private array $accounts = [];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $account,
        private readonly ?string $token = null,
        private readonly string $endpoint = self::ENDPOINT,
        private readonly string $userAgent = 'omnifund (+https://github.com/glitchr-omnisense/omnifund)',
    ) {
    }

    public function getName(): string
    {
        return 'opencollective';
    }

    public function getTitle(): string
    {
        return 'Open Collective';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(profile: true, sponsors: true, tiers: true, key: false, terms: [
            'https://opencollective.com/tos',
            'https://graphql-docs-v2.opencollective.com/',
        ]);
    }

    public function profile(?string $account = null): Profile
    {
        $data = $this->account($account ??= $this->account);
        $slug = Json::string($data, 'slug') ?? $account;
        $budget = self::money(Json::get($data, 'stats', 'yearlyBudget'));

        return new Profile(
            'opencollective',
            $slug,
            Json::string($data, 'name'),
            Json::string($data, 'description'),
            'https://opencollective.com/'.rawurlencode($slug),
            Json::string($data, 'imageUrl'),
            Json::int($data, 'members', 'totalCount'),
            null === $budget ? null : new Income($budget, Period::Year),
        );
    }

    public function sponsors(?string $account = null, ?int $limit = null): array
    {
        $account ??= $this->account;
        $sponsors = [];
        $offset = 0;
        do {
            $page = Json::get($this->query(self::MEMBERS, ['slug' => $account, 'limit' => null === $limit ? self::PAGE : min(self::PAGE, $limit - \count($sponsors)), 'offset' => $offset], "the backers of $account"), 'data', 'account', 'members');
            $nodes = Json::list($page, 'nodes');
            foreach ($nodes as $node) {
                if (null !== $slug = Json::string($node, 'account', 'slug')) {
                    $sponsors[] = new Sponsor(
                        $slug,
                        Json::string($node, 'account', 'name'),
                        Json::string($node, 'account', 'imageUrl'),
                        'https://opencollective.com/'.rawurlencode($slug),
                        match (Json::string($node, 'account', 'type')) {
                            'INDIVIDUAL' => Sponsor::USER,
                            'ORGANIZATION' => Sponsor::ORGANIZATION,
                            default => Sponsor::OTHER,
                        },
                        Json::date($node, 'since'),
                        Json::string($node, 'tier', 'name'),
                        self::money(Json::get($node, 'totalDonations')),
                    );
                }
            }
            $offset += \count($nodes);
        } while ([] !== $nodes && $offset < (Json::int($page, 'totalCount') ?? 0) && (null === $limit || \count($sponsors) < $limit));

        return $sponsors;
    }

    public function tiers(?string $account = null): array
    {
        $tiers = [];
        foreach (Json::list($this->account($account ?? $this->account), 'tiers', 'nodes') as $node) {
            $tiers[] = new Tier(
                Json::string($node, 'name') ?? '',
                self::money(Json::get($node, 'amount')),
                match (Json::string($node, 'frequency')) {
                    'YEARLY' => Period::Year,
                    'ONETIME' => Period::Once,
                    default => Period::Month,
                },
                Json::string($node, 'id'),
                Json::string($node, 'description'),
                'FLEXIBLE' === Json::string($node, 'amountType') || 'FLEXIBLE' === Json::string($node, 'frequency'),
                self::money(Json::get($node, 'minimumAmount')),
            );
        }

        return $tiers;
    }

    /** @return array<mixed> the collective, its tiers and stats - asked once */
    private function account(string $account): array
    {
        if (!isset($this->accounts[$account])) {
            $data = Json::get($this->query(self::ACCOUNT, ['slug' => $account], "the collective $account"), 'data', 'account');
            $this->accounts[$account] = \is_array($data) ? $data : throw ProviderException::answered('Open Collective', "the collective $account", 'no such collective');
        }

        return $this->accounts[$account];
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<mixed>
     */
    private function query(string $query, array $variables, string $what): array
    {
        $headers = ['User-Agent' => $this->userAgent, 'Accept' => 'application/json'];
        if (null !== $this->token) {
            $headers['Personal-Token'] = $this->token;
        }
        $answer = Json::request($this->http, 'POST', $this->endpoint, ['headers' => $headers, 'json' => ['query' => $query, 'variables' => $variables]], 'Open Collective', $what);
        if ([] !== $errors = Json::list($answer, 'errors')) {
            throw ProviderException::answered('Open Collective', $what, implode('; ', array_map(static fn ($e) => Json::string($e, 'message') ?? '?', $errors)));
        }

        return $answer;
    }

    private static function money(mixed $amount): ?Money
    {
        $cents = Json::int($amount, 'valueInCents');
        $currency = Json::string($amount, 'currency');

        return null === $cents || null === $currency ? null : new Money($cents, $currency);
    }
}
