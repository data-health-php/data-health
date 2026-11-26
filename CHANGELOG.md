# Changelog

## [Unreleased](https://github.com/data-health-php/data-health/compare/v0.1.1...HEAD)

## [v0.1.1](https://github.com/data-health-php/data-health/compare/v0.1.0...v0.1.1) - 2026-11-26

### Added

- Explain current finding facts through the optional `CanExplain` contract and `DataHealth::explain()` without changing business data or finding status.
- Read explanations with `health:explain ID` and during interactive record inspection, including unavailable-explanation errors.
- Generate explanation placeholders with `health:make:finding --explain` or configured default interfaces.
- Document explanation fields, safe audience-specific facts, and the optional MCP integration in the README and bundled Boost skill.

## v0.1.0 - 2026-10-26

Initial public release of Data Health for PHP 8.3+ and Laravel 12 and 13.

### Added

- Discover application finding classes with independent detection, verification, and resolution capabilities.
- Persist findings linked to Eloquent models, deduplicate recurring occurrences by model and context, and track active, ignored, and resolved statuses.
- Define finding keys, descriptions, urgency, and worklists through PHP attributes.
- Run detection manually, on a schedule, or through queued jobs, with optional automatic verification and resolution.
- Process large datasets with persistent cursors.
- Generate finding classes and browse, verify, resolve, ignore, or manually close findings through Artisan commands and the interactive console.
- Publish configuration and migrations through Laravel's package discovery and publishing APIs.
- Clean up findings for deleted models through an opt-in relationship concern or global model listener.
- Provide Laravel Boost guidelines, a development skill, and runnable documentation examples.
