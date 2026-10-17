<div align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="artwork/data-health-logo-dark.svg">
        <img src="artwork/data-health-logo.svg" alt="Data Health — Find, Verify, Resolve" width="720">
    </picture>
</div>

<p align="center">
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://img.shields.io/packagist/v/23m/data-health.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://img.shields.io/packagist/php-v/23m/data-health.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://badge.laravel.cloud/badge/23m/data-health?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/23m/data-health/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/23m/data-health/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://img.shields.io/packagist/dt/23m/data-health.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Data Health helps Laravel applications detect, track, verify, and resolve
inconsistencies in their data.

## Documentation

Read the [full documentation](https://data-health-php.github.io/data-health-docs/)
for configuration, defining findings, scheduling detections, queues, worklists,
and cleaning up model findings.

## Installation

You can install the package via Composer:

```bash
composer require data-health/data-health
```

Publish the package resources and run the migrations:

```bash
php artisan vendor:publish --tag="data-health"
php artisan migrate
```

To publish only the migrations or configuration, use the `data-health-migrations`
or `data-health-config` tag.

## Usage

Data Health discovers finding classes in your application and supports scheduled or
queued detection, verification and resolution workflows, urgency and worklist metadata,
and automatic cleanup when related Eloquent models are deleted.

Generate a finding class with Artisan:

```bash
php artisan health:make:finding Customers/DuplicateCustomerFinding --detect --verify --resolve
```

The command creates `app/DataHealth/Customers/DuplicateCustomerFinding.php` by default.
It uses the first directory and namespace in `data-health.directories`; select another
configured directory with `--directory=path/to/findings`. Nested names and fully
qualified names within the selected namespace are supported. Existing files are
protected unless `--force` is supplied.

New findings implement `CanDetect` and include a `detect()` placeholder by default.
Choose the default interfaces in `config/data-health.php`:

```php
'make_finding' => [
    'interfaces' => [
        \DataHealth\Contracts\CanDetect::class,
        // \DataHealth\Contracts\CanVerify::class,
        // \DataHealth\Contracts\CanResolve::class,
    ],
],
```

Use an empty list to generate a plain finding. Command options add to these defaults;
`--schedule`, `--auto-verify`, and `--auto-resolve` still add their required interfaces
and method placeholders.

| Option | Generated behavior |
| --- | --- |
| `--detect`, `--verify`, `--resolve` | Add the matching interface and method placeholder. |
| `--key=`, `--description=`, `--worklist=` | Add the corresponding class metadata attribute. |
| `--urgency=` | Set `immediate`, `soon`, `normal`, or `deferred` urgency. |
| `--schedule="*/5 * * * *"` | Add a cron schedule and detection method. |
| `--async` | Queue scheduled detection; requires `--schedule`. |
| `--queue=`, `--connection=` | Set detection queue routing; require `--async`. |
| `--auto-resolve` | Add automatic resolution and a resolution method. |
| `--auto-verify=0` | Add automatic verification and a verification method; the delay is a non-negative number of minutes. |

For example, scaffold a scheduled finding with queue routing and metadata:

```bash
php artisan health:make:finding DuplicateCustomerFinding \
    --schedule="*/5 * * * *" --async --queue=data-health \
    --verify --resolve --key=duplicate-customer --urgency=soon --worklist=data-quality
```

Implement the generated methods before relying on detection or automatic actions.
The detection placeholder returns `null`; verification and resolution return
`false` until implemented. Use `php artisan help health:make:finding` to see all options.

Pass any context that identifies a finding as the second constructor argument. Data
Health stores that context and uses it to distinguish findings for the same model:

```php
DuplicateCustomerFinding::found($customer, [
    'duplicate_customer_id' => $duplicateCustomer->getKey(),
]);
```

Finding actions can read the same data from the finding's readonly `context` property:

```php
$duplicateCustomerId = $this->context['duplicate_customer_id'];
```

## Running Detection

Run all discovered findings implementing `DataHealth\Contracts\CanDetect`:

```bash
php artisan health:detect
```

Detection runs synchronously by default, including findings without `#[Scheduled]`.
Manual runs ignore cron timing and `data-health.scheduler.enabled`; `#[Async]` only
affects queue routing when `--async` is supplied.

| Parameter | Behavior |
| --- | --- |
| `findings...` | Optional finding keys or fully qualified class names. Omit to run all detectable findings. |
| `--except=...` | Exclude a finding by key or class; repeat for multiple exclusions. |
| `--async` | Dispatch one unique `DetectFinding` job per selected finding. |
| `--queue=`, `--connection=` | Override queue routing; require `--async` and non-empty values. |

```bash
php artisan health:detect DuplicateCustomerFinding MissingInvoiceFinding
php artisan health:detect --except=ExpensiveFinding
php artisan health:detect --async --queue=data-health
```

Async runs use each finding's `#[Async]` queue and connection when present, falling
back to the application's queue defaults. Command-line overrides take precedence
independently. Configure a non-`sync` connection and a running queue worker for
background execution. Jobs already queued or running for the same finding are
skipped using Laravel's unique-job lock.

All selectors, including exclusions, must identify registered detectable findings
and are validated before execution begins. Duplicate selections run once, and
exclusions take precedence. An empty selection succeeds without running detection.

The command reports each finding and a summary of completed or queued detections,
skips, and failures. Individual failures do not stop the remaining findings; the
command returns a nonzero exit code if any detection or dispatch fails. Async
success confirms dispatch, with results handled by the worker. If `detect()` returns
a callable, the command and queued job invoke it through Laravel's container.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Data Health! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Jascha Gerles](https://github.com/23m)
- [All Contributors](../../contributors)

## License

Data Health is open-sourced software licensed under the [MIT license](LICENSE.md).
