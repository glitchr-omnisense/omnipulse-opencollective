<?php

namespace Omnipulse\OpenCollective;

use Omnipulse\Config;
use Omnipulse\ProviderFactory;
use Omnipulse\ProviderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Open Collective, from its options:
 *
 *   account     the collective's slug: "webpack" (required)
 *   token       a personal token, optional: public data is read without one, at 10 calls a minute; a
 *               token (sent as Personal-Token) raises the limit
 *   endpoint    https://api.opencollective.com/graphql/v2
 *   user_agent  sent with every call
 */
final class OpenCollectiveFactory extends ProviderFactory
{
    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnipulse.factory_name' => 'opencollective',
            'omnipulse.factory_title' => 'Open Collective',
            'omnipulse.required_options' => ['account'],
            'token' => '',
            'endpoint' => OpenCollectiveProvider::ENDPOINT,
            'user_agent' => 'omnipulse (+https://github.com/glitchr-omnisense/omnipulse)',
        ]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new OpenCollectiveProvider($this->http, (string) $c->string('account'), $c->string('token'), $c->string('endpoint') ?? OpenCollectiveProvider::ENDPOINT, (string) $c->string('user_agent'));
    }
}
