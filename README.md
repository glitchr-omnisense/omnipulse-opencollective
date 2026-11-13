# omnipulse/opencollective

Open Collective for [omnisense/omnipulse](https://github.com/glitchr-omnisense/omnipulse), under any
PHP host: a collective read through Open Collective's GraphQL API v2 - its profile and yearly
budget, its tiers, its backers. Public data: no key needed.

Formerly `omnifund/opencollective` (omnifund merged into omnipulse on 2026-11-10: `Omnifund\OpenCollective\` is now
`Omnipulse\OpenCollective\`; this package replaces the former name).

## Install and a first call

```sh
composer require omnipulse/opencollective
```

No account for this first call: Open Collective's answer is recorded
(`Omnipulse\Testing\RecordedHttpClient` answers in place of the API), so nothing leaves the process.
A real call needs no account for a collective's public data (a personal token only raises the rate
limit), only an HTTP client.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnipulse\OpenCollective\OpenCollectiveFactory;
use Omnipulse\Testing\RecordedHttpClient;

// Open Collective's answer, recorded: nothing sent. HttpClient::create() (symfony/http-client) for a real call.
$http = RecordedHttpClient::routes([
    'POST /graphql/v2' => '{"data": {"account": {"slug": "octo-collective", "name": "Octo Collective", "description": "Octo is a build tool for the web.", "members": {"totalCount": 3}, "stats": {"yearlyBudget": {"valueInCents": 1250000, "currency": "USD"}}}}}',
]);
$opencollective = (new OpenCollectiveFactory($http))->create(['account' => 'octo-collective']);

$profile = $opencollective->profile();
echo $profile->name, ' ', $profile->sponsors, ' members, ', $profile->income?->amount->decimal(), ' ', $profile->income?->amount->currency, " a year\n";   // Octo Collective 3 members, 12500.00 USD a year
```

With your credentials, through the family's registry:

```php
use Omnipulse\OpenCollective\OpenCollectiveFactory;
use Omnipulse\Registry;

$registry = new Registry([new OpenCollectiveFactory($httpClient)], [
    'oc' => ['factory' => 'opencollective', 'options' => ['account' => 'webpack']],
]);
$registry->tiers('oc')->tiers();
```

Options: `account` (the collective's slug, required), `token` (a personal token, sent as
`Personal-Token`, optional: it raises the rate limit), `endpoint`, `user_agent`.

- **Profile**: name, description, page, logo, the backers counted, the yearly budget Open
  Collective estimates (`Income` by the year). No goal: the API gives none in a stable form.
- **Sponsors**: the members with the BACKER role, 100 a page (offset), each with since when, its
  tier and its total given; individuals, organizations, and other collectives as `other`.
- **Tiers**: monthly, yearly or one-time; a flexible amount marked as custom, with its minimum.

No library is wrapped: none on Packagist covers Open Collective's API v2.

## Terms

Open Collective's [Terms of Service](https://opencollective.com/tos) and its
[API documentation](https://graphql-docs-v2.opencollective.com/). Without a token the API allows
**10 calls a minute** (answered `x-ratelimit-limit: 10`): cache what you read; the profile and
tiers cost one call, each page of backers one more.

## Usage policy

Open Collective's usage policy is declared as data in [`policy.json`](policy.json) (omnisense/contracts'
schema, named in `composer.json`'s `extra.omnisense.policy`), its terms read on
2026-11-10 (https://graphql-docs-v2.opencollective.com/): no rate limit declared (none published); commercial use and redistribution under contract (Terms of Service 11, 13(b)); no key required. omnipulse's registry enforces it - a call over a
limit is never made, a 429 and its `Retry-After` hold every next call
(`UsageLimitReachedException`) - and a site may tighten it, never loosen it
(omnisense/omnipulse's docs/policies.md).

## Verified, and not

- Verified on recorded answers (the shapes of live answers of 2026-10-24, the people invented):
  the profile and its budget by the month, the tiers (fixed, flexible, yearly), the backers page
  after page, a limit, an unknown collective, the rate limit, the token header sent only when
  given; `BareTest`; PHPStan at the highest level.
- Verified live without a key (2026-10-24): webpack's profile, tiers and three backers.
- Not verified: a personal token.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Open Collective. Its MIT licence covers this package's code only and grants no right
to the service or its data. Whoever installs and runs it is responsible for complying with Open
Collective's terms and for any use made of what it returns; the authors accept no liability for
how it is used (see [LICENSE](LICENSE)).

## Licence

MIT.
