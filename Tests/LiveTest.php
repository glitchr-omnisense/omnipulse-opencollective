<?php

namespace Omnifund\OpenCollective\Tests;

use Omnifund\OpenCollective\OpenCollectiveProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Open Collective's API itself, without a key: two calls (10 a minute are
 * allowed), public data of a long-standing collective. `docker compose run
 * --rm omnifund live`; never in the default run.
 */
#[Group('live')]
final class LiveTest extends TestCase
{
    public function testACollectiveItsTiersAndBackers(): void
    {
        $provider = new OpenCollectiveProvider(HttpClient::create(['timeout' => 20]), 'webpack', userAgent: 'omnifund-tests/1.0 (+https://github.com/glitchr-studio/omnifund)');

        $profile = $provider->profile();
        self::assertSame('webpack', $profile->handle);
        self::assertGreaterThan(100, $profile->sponsors);
        self::assertNotEmpty($provider->tiers(), 'from the same answer');
        $backers = $provider->sponsors(limit: 3);
        self::assertCount(3, $backers);
        self::assertNotNull($backers[0]->since);
    }
}
