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

## How findings work

Each finding expresses an application rule. Implement only the capabilities it needs:

| Operation | Scope | Responsibility | Inline result |
| --- | --- | --- | --- |
| `detect()` | Candidate records | Report each occurrence with `found()`. | `null` |
| `verify()` | One occurrence's model and context, including relevant related data | Check whether the problem has disappeared without changing business data. | `bool` |
| `resolve()` | One occurrence's model and context | Recheck applicability and apply a known correction using application logic. | `bool` |

Assess resolution for every finding. Implement it when application rules establish a
deterministic correction; explain when human judgment is required. **Implementing
`resolve()` exposes an action. It does not execute it automatically.** `#[AutoResolve]`
separately opts into resolution when a finding record is first created.

A `true` verification or resolution result marks the record resolved; `false` leaves
its status unchanged. Throw when invalid input or an operational failure prevents a
reliable result. Detection does not resolve records merely because a scan stops reporting them.

**For coding agents:** read the [Data Health development skill](resources/boost/skills/data-health-development/SKILL.md)
before generating findings or scanning an application for inconsistencies. With Laravel
Boost, run `php artisan boost:install` and select the package guidelines and skill;
use `php artisan boost:update` to refresh installed guidance. Without Boost, read the
bundled skill directly in `vendor/data-health/data-health/resources/boost/skills/data-health-development/SKILL.md`.

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

declare(strict_types=1);

namespace App\DataHealth;

use App\Models\Order;
use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Finding;
use Illuminate\Support\Facades\DB;

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

        if (! $order instanceof Order) {
            throw new \LogicException('Expected an Order model.');
        }

        return $order->paid_at === null || $order->status !== 'pending';
    }

    public function resolve(): bool
    {
        if (! $this->model instanceof Order) {
            throw new \LogicException('Expected an Order model.');
        }

        return DB::transaction(function (): bool {
            $order = Order::query()->lockForUpdate()->findOrFail($this->model->getKey());

            if ($order->paid_at === null || $order->status !== 'pending') {
                return true;
            }

            return $order->update(['status' => 'paid']);
        });
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

The resolver rechecks the current state while locking the order, so a later valid
status is preserved and repeated resolution is safe. Reuse an existing domain action
when the application owns this transition. The generator's unfinished methods throw
explicit exceptions until you replace them. For supported delegation and optional
detection counts, see the [contract reference](https://data-health-php.github.io/data-health-docs/reference/attributes-and-contracts/).

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
