<?php

namespace Omnipulse\OpenCollective\Tests;

use Omnipulse\Exception\ProviderException;
use Omnipulse\Model\Period;
use Omnipulse\Model\Sponsor;
use Omnipulse\OpenCollective\OpenCollectiveFactory;
use Omnipulse\OpenCollective\OpenCollectiveProvider;
use Omnipulse\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Open Collective's answers recorded (the shapes of live answers of
 * 2026-10-24, the people invented), each request checked.
 */
final class OpenCollectiveProviderTest extends TestCase
{
    /** @var list<array{method: string, url: string, options: array<mixed>}> */
    private array $requests = [];

    public function testTheProfile(): void
    {
        $profile = $this->provider(['account'])->profile();

        self::assertSame(['opencollective', 'octo-collective', 'Octo Collective'], [$profile->provider, $profile->handle, $profile->name]);
        self::assertSame('https://opencollective.com/octo-collective', $profile->url);
        self::assertSame(3, $profile->sponsors);
        $income = $profile->income;
        self::assertNotNull($income);
        self::assertSame('179852.31', $income->amount->decimal());
        self::assertSame(Period::Year, $income->period);
        self::assertSame('14987.69', $income->monthly()?->decimal());
        self::assertNull($profile->goal);

        self::assertSame(['POST', 'https://api.opencollective.com/graphql/v2'], [$this->requests[0]['method'], $this->requests[0]['url']]);
        self::assertSame(['slug' => 'octo-collective'], $this->body(0)['variables']);
        self::assertNotContains('Personal-Token: ', array_map(static fn (string $h) => substr($h, 0, 16), $this->headers(0)), 'no token: none sent');
    }

    public function testTheTiers(): void
    {
        $provider = $this->provider(['account']);
        $tiers = $provider->tiers();
        $provider->profile();

        self::assertCount(1, $this->requests, 'the collective asked once');
        self::assertSame(['Backer', '10.00', Period::Month, false, '2.00'], [$tiers[0]->name, $tiers[0]->price?->decimal(), $tiers[0]->period, $tiers[0]->customAmount, $tiers[0]->minimum?->decimal()]);
        self::assertSame(['Donation', null, Period::Once, true, '1.00'], [$tiers[1]->name, $tiers[1]->price, $tiers[1]->period, $tiers[1]->customAmount, $tiers[1]->minimum?->decimal()]);
        self::assertSame(['Yearly sponsor', '1000.00', Period::Year, null], [$tiers[2]->name, $tiers[2]->price?->decimal(), $tiers[2]->period, $tiers[2]->minimum]);
    }

    public function testTheBackersPageAfterPage(): void
    {
        $sponsors = $this->provider(['members-1', 'members-2'], 'oc-token')->sponsors();

        self::assertSame(['ada', 'acme', 'friends-of-octo'], array_map(static fn (Sponsor $s) => $s->handle, $sponsors));
        self::assertSame([Sponsor::USER, Sponsor::ORGANIZATION, Sponsor::OTHER], array_map(static fn (Sponsor $s) => $s->kind, $sponsors));
        self::assertSame('2016-10-15', $sponsors[0]->since?->format('Y-m-d'));
        self::assertSame(['Backer', null], [$sponsors[0]->tier, $sponsors[1]->tier]);
        self::assertSame('500.00', $sponsors[1]->total?->decimal());
        self::assertSame('https://opencollective.com/ada', $sponsors[0]->url);
        self::assertSame(['slug' => 'octo-collective', 'limit' => 100, 'offset' => 0], $this->body(0)['variables']);
        self::assertSame(['slug' => 'octo-collective', 'limit' => 100, 'offset' => 2], $this->body(1)['variables']);
        self::assertContains('Personal-Token: oc-token', $this->headers(0));
    }

    public function testALimit(): void
    {
        $sponsors = $this->provider(['members-1'])->sponsors('another', 2);

        self::assertCount(2, $sponsors);
        self::assertCount(1, $this->requests);
        self::assertSame(['slug' => 'another', 'limit' => 2, 'offset' => 0], $this->body(0)['variables']);
    }

    public function testACollectiveOpenCollectiveDoesNotKnow(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Open Collective answered No collective found with slug nobody-here-0 for the collective nobody-here-0.');
        $this->provider(['not-found'])->profile('nobody-here-0');
    }

    public function testTheRateLimit(): void
    {
        $provider = new OpenCollectiveProvider(new MockHttpClient(new MockResponse('{"error":"Rate limit exceeded"}', ['http_code' => 429])), 'octo-collective');

        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage('Open Collective answered 429 for the collective octo-collective.');
        $provider->tiers();
    }

    public function testTheFactory(): void
    {
        $registry = new Registry([new OpenCollectiveFactory(new MockHttpClient())], ['oc' => ['factory' => 'opencollective', 'options' => ['account' => 'octo-collective']]]);

        self::assertInstanceOf(OpenCollectiveProvider::class, $registry->get('oc'));
        self::assertFalse($registry->get('oc')->capabilities()->key);
    }

    /** @param list<string> $answers */
    private function provider(array $answers, ?string $token = null): OpenCollectiveProvider
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$answers): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.array_shift($answers).'.json'));
        });

        return new OpenCollectiveProvider($http, 'octo-collective', $token);
    }

    /** @return list<string> */
    private function headers(int $request): array
    {
        $headers = $this->requests[$request]['options']['headers'] ?? [];

        return \is_array($headers) ? array_values(array_filter($headers, 'is_string')) : [];
    }

    /** @return array<string, mixed> */
    private function body(int $request): array
    {
        $body = json_decode(\is_string($b = $this->requests[$request]['options']['body'] ?? null) ? $b : '', true);
        self::assertIsArray($body);

        /** @var array<string, mixed> $body */
        return $body;
    }
}
