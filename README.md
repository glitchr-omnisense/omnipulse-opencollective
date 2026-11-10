# omnipulse/opencollective

Open Collective for [omnisense/omnipulse](https://github.com/glitchr-omnisense/omnipulse), under any
PHP host: a collective read through Open Collective's GraphQL API v2 - its profile and yearly
budget, its tiers, its backers. Public data: no key needed.

Formerly `omnifund/opencollective` (omnifund merged into omnipulse on 2026-11-10: `Omnifund\OpenCollective\` is now
`Omnipulse\OpenCollective\`; this package replaces the former name).

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
