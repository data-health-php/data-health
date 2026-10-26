<div align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="artwork/data-health-logo-dark.svg">
        <img src="artwork/data-health-logo.svg" alt="Data Health — Find, Verify, Resolve" width="720">
    </picture>
</div>

<p align="center">
    <a href="https://packagist.org/packages/data-health/data-health"><img src="https://img.shields.io/packagist/v/data-health/data-health.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/data-health/data-health"><img src="https://img.shields.io/packagist/php-v/data-health/data-health.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/data-health/data-health"><img src="https://badge.laravel.cloud/badge/data-health/data-health?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/data-health-php/data-health/actions/workflows/tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/data-health-php/data-health/tests.yml?branch=main&label=Tests&style=flat-square" alt="Test suite status"></a>
</p>

<p align="center">
    <a href="https://data-health-php.github.io/data-health-docs/">Documentation</a> ·
    <a href="https://data-health-php.github.io/data-health-docs/start-here/quick-start/">Quick start</a> ·
    <a href="https://data-health-php.github.io/data-health-docs/filament/overview/">Filament integration</a>
</p>

**Find, track, and fix inconsistent data in Laravel.**

Paid orders still marked as pending. Duplicate customer accounts. Subscriptions
that disagree with your billing provider. Some data problems only become visible
across records, after an import, or when an external system changes.

Data Health turns these problems into persistent **findings** linked to your
Eloquent models. Define your application's rules in PHP, review affected records,
and apply a known correction when one exists. The package handles discovery,
deduplication, and status tracking.

## Why Data Health?

- **Turn cleanup scripts into a repeatable workflow.** Keep each check in a finding class that Data Health discovers automatically.
- **Keep an actionable list of problems.** Repeated detection updates the same record. Resolved findings reopen if the problem returns; ignored findings stay ignored.
- **Keep corrections under your control.** Review findings manually, verify fixes made elsewhere, or expose a resolver that uses your application's logic. Automatic resolution is a separate opt-in.
- **Fit checks around your application.** Run detection from Artisan or PHP, schedule it with Laravel, or dispatch it to a queue. Batch expensive checks with persistent cursors.
- **Give your team an interface.** Use the included interactive console or add the optional Filament dashboard, with the same finding classes behind both.

Start with one inconsistency you already know how to recognize.

## Installation

Requires **PHP 8.3+** and **Laravel 12 or 13**. Filament is optional.

```bash
composer require data-health/data-health:^0.1
php artisan vendor:publish --tag="data-health"
php artisan migrate
```

Laravel discovers the package automatically. **Using UUID or ULID model keys?**
Adapt the published morph columns before migrating. See the
[installation guide](https://data-health-php.github.io/data-health-docs/start-here/installation/)
for schema guidance and publishing options.

## Your first finding

Suppose your application considers an order inconsistent when `paid_at` is set
but its string `status` is still `pending`. Generate a finding:

```bash
php artisan health:make:finding PaidOrderMarkedPending
```

Replace `app/DataHealth/PaidOrderMarkedPending.php` with:

```php
<?php

declare(strict_types=1);

namespace App\DataHealth;

use App\Models\Order;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

class PaidOrderMarkedPending extends Finding implements CanDetect
{
    public static function detect(): null
    {
        Order::query()
            ->whereNotNull('paid_at')
            ->where('status', 'pending')
            ->eachById(fn (Order $order) => self::found($order));

        return null;
    }
}
```

Run detection, then open the interactive console to review the results:

```bash
php artisan health:detect PaidOrderMarkedPending -n
php artisan health
```

Each matching order gets an active finding record linked to that order. Run the
check again and Data Health refreshes those records instead of creating duplicates.
This example records problems without changing order data.

The [quick start](https://data-health-php.github.io/data-health-docs/start-here/quick-start/)
builds on this example with verification and a resolver that safely corrects the
order's status.

## From finding to fix

A finding can support three independent capabilities. Add only the ones it needs:

| Capability | What you implement | What your team can do |
| --- | --- | --- |
| **Detect** (`CanDetect`) | Search for problems and report each occurrence with `found()`. | Build and refresh a list of affected records. |
| **Verify** (`CanVerify`) | Check whether one recorded problem still exists, without changing business data. | Confirm a fix made elsewhere and close the finding. |
| **Resolve** (`CanResolve`) | Recheck the current state and apply a known application correction. | Fix the underlying data and close the finding. |

A successful verification or resolution marks the record resolved. Detection alone
does not close findings when they stop appearing in a scan. Implementing a resolver
makes an action available; it does not run it automatically.

Keep findings that need human judgment for manual review. For deterministic fixes,
reuse your application's domain actions and make repeated resolution safe.

Read the guides to
[verification](https://data-health-php.github.io/data-health-docs/guides/verifying-findings/)
and [resolution](https://data-health-php.github.io/data-health-docs/guides/resolving-findings/),
or [schedule and queue your checks](https://data-health-php.github.io/data-health-docs/guides/scheduling-and-queues/)
when you are ready to run them regularly.

## A dashboard for your team

The optional **[Data Health Filament](https://data-health-php.github.io/data-health-docs/filament/overview/)**
package adds a Filament 5 interface for searching and filtering findings, inspecting
affected records, assigning work, running bulk actions, and viewing statistics.
You can also embed its tables in Blade or Livewire views.

It uses the same findings and records as the core package. It is currently
available on the development branch:

```bash
composer require data-health/data-health-filament:dev-main
```

Follow the
[Filament setup guide](https://data-health-php.github.io/data-health-docs/filament/installation/)
to add it to your application.

## Documentation

Use the **[full documentation](https://data-health-php.github.io/data-health-docs/)**
for tutorials, operational guidance, and the API reference.

| I want to… | Read |
| --- | --- |
| Build a finding from detection through resolution | [Quick start](https://data-health-php.github.io/data-health-docs/start-here/quick-start/) |
| Understand identity, deduplication, and statuses | [Findings and records](https://data-health-php.github.io/data-health-docs/core-concepts/findings-and-records/) |
| Distinguish multiple problems on the same model | [Finding context](https://data-health-php.github.io/data-health-docs/guides/context/) |
| Browse findings and run actions from a terminal | [CLI guide](https://data-health-php.github.io/data-health-docs/cli/overview/) |
| Prioritize problems and group work | [Urgency and worklists](https://data-health-php.github.io/data-health-docs/guides/metadata-and-worklists/) |
| Limit work per run or check a rate-limited API | [Large datasets](https://data-health-php.github.io/data-health-docs/guides/large-datasets/) |
| Customize discovery, configuration, or capabilities | [Configuration](https://data-health-php.github.io/data-health-docs/reference/configuration/) · [Attributes and contracts](https://data-health-php.github.io/data-health-docs/reference/attributes-and-contracts/) |

## Coding agents and Laravel Boost

Data Health includes Laravel Boost guidelines and a
[development skill](resources/boost/skills/data-health-development/SKILL.md) to help
coding agents design findings from your application's rules. With Boost installed,
run `php artisan boost:install` and select the package guidelines and skill; use
`php artisan boost:update` to refresh them.

Without Boost, ask your agent to read
`vendor/data-health/data-health/resources/boost/skills/data-health-development/SKILL.md`
before generating findings. See the
[agent setup guide](https://data-health-php.github.io/data-health-docs/start-here/installation/#coding-agents-and-laravel-boost)
for details.

## Contributing

Contributions and bug reports are welcome. See the
[contributing guide](.github/CONTRIBUTING.md) for setup and testing, or
[open an issue](https://github.com/data-health-php/data-health/issues) to report a bug.
Please follow the [security policy](.github/SECURITY.md) when reporting vulnerabilities.
Release history is available in the [changelog](CHANGELOG.md).

## Credits and license

Created by Jascha Gerles and [all contributors](https://github.com/data-health-php/data-health/graphs/contributors).
Released under the [MIT license](LICENSE.md).
