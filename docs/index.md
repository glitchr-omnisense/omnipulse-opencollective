# omnipulse/opencollective

Open Collective for [omnisense/omnipulse](https://github.com/glitchr-omnisense/omnipulse): a
collective read through Open Collective's GraphQL API v2
(`POST https://api.opencollective.com/graphql/v2`) - its profile and yearly budget, its tiers,
its backers. Public data: no key needed. Two queries: the account (`account(slug)`: profile,
tiers, stats, the backers counted) and its members with the `BACKER` role, 100 a page.

## Installation

```sh
composer require omnipulse/opencollective symfony/http-client
```

Requires PHP 8.2, `omnisense/omnipulse` (1.x) and `symfony/http-client-contracts` (^3.0): the
application gives the HTTP client to the factory's constructor. No library is wrapped: none on
Packagist covers Open Collective's API v2.

## Credentials

None for public data. A **personal token** of an Open Collective account raises the rate limit from 10 calls a minute; it is sent as `Personal-Token`.

## Options

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `account` | string | - | yes | the collective's slug: `webpack` |
| `token` | string | none | no | a personal token, sent as `Personal-Token` when set |
| `endpoint` | string | `https://api.opencollective.com/graphql/v2` | no | |
| `user_agent` | string | `omnipulse (+https://github.com/glitchr-omnisense/omnipulse)` | no | sent with every call |

## Capabilities

| Contract | Supported |
|---|---|
| `ProfileInterface` | yes (no goal) |
| `SponsorsInterface` | yes: the backers |
| `TiersInterface` | yes |

`capabilities()`: profile, sponsors and tiers; `key` false; terms: opencollective.com/tos and the
API v2 documentation.

## Behaviour

**The account.** One query per slug, kept in the provider object: `profile()` and `tiers()` for
the same collective cost one call. A slug Open Collective does not know is
`Open Collective answered no such collective for the collective <slug>.`

**Profile.** `handle` the slug, `name`, `description`, `url` `https://opencollective.com/<slug>`,
`avatarUrl` the logo (`imageUrl`), `sponsors` the backers counted (`members(role: BACKER)
.totalCount`), `income` the yearly budget Open Collective estimates (`stats.yearlyBudget`), by the
year: `monthly()` gives a twelfth. No goal: the API gives none in a stable form.

**Sponsors.** The members with the `BACKER` role, by `limit` and `offset`, 100 a page (or no more
than the limit leaves), until the total count is reached or a page comes back empty. Each:
the account's slug, name and picture, the page `https://opencollective.com/<slug>`, the kind
(`INDIVIDUAL` user, `ORGANIZATION` organization, anything else - a collective - other), `since`,
the tier's name, `totalDonations` as `total`.

**Tiers.** Each: name, `amount` (`null` when the amount is left to the giver), the frequency
(`MONTHLY` month, `YEARLY` year, `ONETIME` once, anything else month), id, description, custom
amount when the amount type or the frequency is `FLEXIBLE`, `minimumAmount`.

**Calls.** `POST` with the `User-Agent`, `Accept: application/json`, `Personal-Token` when a
token is set, and `{"query", "variables"}`. A GraphQL `errors` member is a `ProviderException`
joining the messages. Amounts are Open Collective's `valueInCents` and `currency`.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnipulse\OpenCollective\OpenCollectiveFactory;
use Omnipulse\Registry;
use Symfony\Component\HttpClient\HttpClient;

$registry = new Registry([new OpenCollectiveFactory(HttpClient::create(['timeout' => 10]))], [
    'oc' => ['factory' => 'opencollective', 'options' => ['account' => 'webpack']],
]);

$profile = $registry->profile('oc')->profile();
echo $profile->name, ': ', $profile->sponsors, ' backers, ', $profile->income?->monthly()?->decimal(), " a month\n";
foreach ($registry->sponsors('oc')->sponsors(limit: 3) as $backer) {
    echo '- ', $backer->label(), ' since ', $backer->since?->format('Y'), ', ', $backer->total?->decimal(), "\n";
}
```

On the package's recorded answers (run on 2026-10-26 in the family's harness):

```
Open Collective (profile sponsors tiers): Octo Collective, 3 sponsors, 179852.31 USD a year (14987.69 a month)
  sponsor Ada L. (user), Backer
  sponsor ACME (organization)
  tier Backer: 10.00 month
  tier Donation: - once, any amount
  tier Yearly sponsor: 1000.00 year
```

## Errors

- A status of 300 or more: `ProviderException` `Open Collective answered <status> for the
  collective <slug>.` (or `the backers of <slug>`); the rate limit is a 429.
- A GraphQL `errors` member, no such collective, a body that is not JSON: `ProviderException`.
- A network failure: `UnreachableException`.

## Verified, and not

`Tests/OpenCollectiveProviderTest.php`, on recorded answers (the shapes of live answers of
2026-10-24, the people invented): the profile and its budget, the tiers (fixed, flexible,
yearly), the backers page after page, a limit, an unknown collective, the rate limit, the
factory. Green in the family's harness on 2026-10-26. Live without a key (README, 2026-10-24):
webpack's profile, tiers and three backers (`Tests/LiveTest.php`, group `live`). Not verified: a
personal token.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by Open Collective. Whoever runs it complies with Open Collective's [Terms of
Service](https://opencollective.com/tos) and its [API documentation](https://graphql-docs-v2.opencollective.com/)
(10 calls a minute without a token); see the README's Responsibility section.
