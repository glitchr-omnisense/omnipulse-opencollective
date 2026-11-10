# Changelog

## Unreleased (1.x)

### Changed
- `omnifund/opencollective` is now `omnipulse/opencollective` (2026-11-10: omnireview and omnifund merged into omnipulse,
  Marco's decision; this repository's history kept, from omnifund-opencollective at 70f4f8d): the namespace
  `Omnipulse\OpenCollective`, requiring `omnisense/omnipulse`, whose one Capabilities and Registry serve the
  funding contracts beside the audience's and the reviews'; `composer.json` replaces
  `omnifund/opencollective`, so that a project never installs both. The factory's name is unchanged (`opencollective`).
  A Registry's unknown provider is now `No "x" provider` (was "funding provider").
