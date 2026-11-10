<?php

namespace Omnipulse\OpenCollective\Tests;

use Omnipulse\Exception\OmnipulseException;
use Omnipulse\Exception\UsageLimitReachedException;
use Omnipulse\OpenCollective\OpenCollectiveFactory;
use Omnipulse\OpenCollective\OpenCollectiveProvider;
use Omnipulse\Registry;
use Omnipulse\Testing\RecordedHttpClient;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * OpenCollectiveProvider's usage policy (policy.json) through omnipulse's registry:
 * as declared, a site's limit counted with the call over it never made,
 * a 429 and its Retry-After holding every next call, a tightening accepted
 * and a looser one refused. No network: recorded answers only.
 */
final class PolicyTest extends TestCase
{
    private const OPTIONS = ['account' => 'octo-collective'];

    public function testThePolicyIsTheOneDeclared(): void
    {
        $policy = (new Registry([new OpenCollectiveFactory(RecordedHttpClient::routes([]))], ['p' => ['factory' => 'opencollective', 'options' => self::OPTIONS]]))->policy('p');

        self::assertFileExists(OpenCollectiveProvider::POLICY);
        self::assertEquals(UsagePolicy::fromFile(OpenCollectiveProvider::POLICY), $policy);
        self::assertSame('opencollective', $policy->provider);
        self::assertSame('2026-11-10', $policy->provenance->verifiedAt);
        self::assertNull($policy->rateLimits, 'no figure published: unknown, never invented');
        self::assertSame(false, $policy->requirements->key);
    }

    public function testASitesLimitIsCountedAndTheCallOverItIsNeverMade(): void
    {
        $http = RecordedHttpClient::routes([]);
        $registry = new Registry([new OpenCollectiveFactory($http)], ['p' => ['factory' => 'opencollective', 'options' => self::OPTIONS, 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'day', 'operations' => ['profile']]]]]]);

        try {
            $registry->profile('p')->profile();
        } catch (UsageLimitReachedException $e) {
            throw $e;
        } catch (OmnipulseException) {
            // Nothing recorded: the provider's own answer to that, counted all the same.
        }
        $made = \count($http->requests);
        self::assertGreaterThan(0, $made);
        try {
            $registry->profile('p')->profile();
            self::fail('The call over the site\'s limit was made.');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmnipulseException::class, $e, 'the family\'s catch holds it');
            self::assertSame('1/day/application/profile', $e->limit?->id());
        }
        self::assertCount($made, $http->requests, 'the refused call reached nothing');
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $http = new RecordedHttpClient(static fn (): array => [429, '{"message":"too many requests"}', ['retry-after' => ['120']]]);
        $registry = new Registry([new OpenCollectiveFactory($http)], ['p' => ['factory' => 'opencollective', 'options' => self::OPTIONS]]);

        try {
            $registry->profile('p')->profile();
            self::fail('A 429 was read as an answer.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(120, $e->retryAfter);
            self::assertSame('opencollective answered 429 Too Many Requests: try again in 120 s.', $e->getMessage());
        }
        $made = \count($http->requests);
        try {
            $registry->profile('p')->profile();
            self::fail('A call went out while the provider asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(119, $e->retryAfter);
        }
        self::assertCount($made, $http->requests, 'the wait held the next request back');
    }

    public function testATighteningIsAccepted(): void
    {
        $registry = new Registry([new OpenCollectiveFactory(RecordedHttpClient::routes([]))], ['p' => ['factory' => 'opencollective', 'options' => self::OPTIONS, 'policy' => ['rateLimits' => [['max' => 10, 'window' => 'minute']], 'rights' => ['caching' => ['maxTtl' => 0]]]]]);

        $policy = $registry->policy('p');
        self::assertContains('10/minute/application/*', array_map(static fn ($l) => $l->id(), $policy->rateLimits ?? []));
        self::assertSame(0, $policy->rights->caching->maxTtl);
    }

    public function testASiteCannotLoosenWhatTheTermsLeaveUnknown(): void
    {
        $registry = new Registry([new OpenCollectiveFactory(RecordedHttpClient::routes([]))], ['p' => ['factory' => 'opencollective', 'options' => self::OPTIONS, 'policy' => ['rights' => ['commercial' => 'allowed']]]]);

        $this->expectException(InvalidPolicyException::class);
        $registry->profile('p');
    }
}
