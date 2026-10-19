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
    <a href="https://github.com/data-health-php/data-health/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/data-health-php/data-health/tests.yml?branch=main&label=Tests&style=flat-square"></a>
</p>

**Find, track, and fix inconsistent data in Laravel.**

Paid orders still marked as pending. Duplicate customers. Missing relationships.
Data Health turns these problems into findings your team can inspect, verify, and
resolve, with each occurrence linked to the affected Eloquent model.

You define what unhealthy data looks like and how to fix it. Data Health handles
discovery, persistence, deduplication, and the finding lifecycle.

- **Write rules in PHP.** Each finding is a class with only the capabilities it needs.
- **Keep track of every issue.** Repeated detections update the same record. Resolved findings reopen if the problem returns.
- **Automate at your own pace.** Run checks manually, on a schedule, or through Laravel queues. Opt into automatic verification or resolution per finding.
- **Work where you already are.** Inspect findings and run actions from Artisan, application code, or the optional Filament interface.

[Documentation](https://data-health-php.github.io/data-health-docs/) ·
[Quick start](https://data-health-php.github.io/data-health-docs/start-here/quick-start/)

## Installation

Requires **PHP 8.3+** and **Laravel 12 or 13**.

```bash
composer require data-health/data-health
php artisan vendor:publish --tag="data-health"
php artisan migrate
```

Laravel discovers the package automatically. See the
[installation guide](https://data-health-php.github.io/data-health-docs/start-here/installation/)
for publishing options and applications using UUID or ULID model keys.

## One class, from detection to resolution

For an application with an `Order` model, a `paid_at` timestamp, and a string
`status`, a finding can detect paid orders that are still pending and correct them.

Generate the class:

```bash
php artisan health:make:finding PaidOrderMarkedPending --verify --resolve
```

Replace the generated class in `app/DataHealth/PaidOrderMarkedPending.php`:

```php
<?php

namespace App\DataHealth;

use App\Models\Order;
use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Finding;

class PaidOrderMarkedPending extends Finding implements CanDetect, CanVerify, CanResolve
{
    public static function detect(): null
    {
        Order::query()
            ->whereNotNull('paid_at')
            ->where('status', 'pending')
            ->eachById(fn (Order $order) => self::found($order));

        return null;
    }

    public function verify(): bool
    {
        $order = $this->model->refresh();

        return $order->paid_at === null || $order->status !== 'pending';
    }

    public function resolve(): bool
    {
        return $this->model->update(['status' => 'paid']);
    }
}
```

Run detection, then open the interactive console to review the results:

```bash
php artisan health:detect PaidOrderMarkedPending -n
php artisan health
```

Each matching order gets a persistent finding record. **Verify** checks whether the
problem has already been fixed elsewhere; **resolve** applies your correction. A
`true` result marks the finding resolved.

Detection, verification, and resolution are independent: implement only what a
finding needs. The [quick start](https://data-health-php.github.io/data-health-docs/start-here/quick-start/)
walks through the complete lifecycle, and the
[CLI guide](https://data-health-php.github.io/data-health-docs/cli/overview/)
covers browsing, filtering, and acting on findings.

## A dashboard for your team

The optional [Data Health Filament](https://data-health-php.github.io/data-health-docs/filament/overview/)
package adds searchable findings, bulk actions, and statistics to Filament 5.
It uses the same finding classes and records, so your checks work across both
interfaces. See the [Filament setup guide](https://data-health-php.github.io/data-health-docs/filament/installation/).

## Documentation

The [full documentation](https://data-health-php.github.io/data-health-docs/) covers
[configuration](https://data-health-php.github.io/data-health-docs/reference/configuration/),
[scheduling and queues](https://data-health-php.github.io/data-health-docs/guides/scheduling-and-queues/),
[finding context](https://data-health-php.github.io/data-health-docs/guides/context/),
[urgency and worklists](https://data-health-php.github.io/data-health-docs/guides/metadata-and-worklists/),
and [large datasets](https://data-health-php.github.io/data-health-docs/guides/large-datasets/).

## Contributing

See the [contributing guide](.github/CONTRIBUTING.md) for setup and testing,
the [changelog](CHANGELOG.md) for changes, and the
[security policy](.github/SECURITY.md) for reporting vulnerabilities.

## Credits and license

Created by Jascha Gerles and [all contributors](https://github.com/data-health-php/data-health/graphs/contributors).
Released under the [MIT license](LICENSE.md).
