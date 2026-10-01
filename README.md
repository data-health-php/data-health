<div align="center">
    <h1>Data Health</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://img.shields.io/packagist/v/23m/data-health.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://img.shields.io/packagist/php-v/23m/data-health.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://badge.laravel.cloud/badge/23m/data-health?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/23m/data-health/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/23m/data-health/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/23m/data-health"><img src="https://img.shields.io/packagist/dt/23m/data-health.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Check for inconsistencies in your data and fix them

## Installation

You can install the package via Composer:

```bash
composer require 23m/data-health
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="data-health"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="data-health-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="data-health-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="data-health-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="data-health-lang"
```

### Publishing the Public Assets

```bash
php artisan vendor:publish --tag="data-health-assets"
```

## Configuration

Scheduled detections are enabled by default. To prevent Data Health from registering its scheduled detections, set the following environment variable:

```dotenv
DATA_HEALTH_SCHEDULER_ENABLED=false
```

The corresponding configuration value is `data-health.scheduler.enabled`.

Scheduled detections are named, prevented from overlapping, and limited to one scheduler server per cron occurrence. Multi-server deployments must use a shared `database`, `memcached`, `dynamodb`, or `redis` cache store so Laravel can coordinate these locks across servers.

## Usage

### Defining a Stable Finding Key

Add the `Key` attribute to use an explicit value as the finding's database key:

```php
use DataHealth\Attributes\Key;
use DataHealth\Finding;

#[Key('duplicate-customer')]
class DuplicateCustomerFinding extends Finding
{
    // ...
}
```

Findings without the attribute continue to use their class basename, such as `DuplicateCustomerFinding`.

### Describing Findings and Their Actions

Use the `Description` attribute on a finding class and on its `detect`, `verify`, or `resolve` methods to provide user-facing explanations:

```php
use DataHealth\Attributes\Description;
use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Finding;

#[Description('The customer appears more than once with the same email address.')]
class DuplicateCustomerFinding extends Finding implements CanDetect, CanResolve, CanVerify
{
    #[Description('Searches customer records for duplicate email addresses.')]
    public static function detect(): int
    {
        // ...
    }

    #[Description('Checks whether the duplicate customer records still exist.')]
    public function verify(): bool
    {
        // ...
    }

    #[Description('Merges the duplicate records into the oldest customer record.')]
    public function resolve(): bool
    {
        // ...
    }
}
```

Read the descriptions for display in a user interface with `DuplicateCustomerFinding::getDescription()` and `DuplicateCustomerFinding::getMethodDescription('detect')`. Missing class, method, or method descriptions return `null`.

### Running Scheduled Detections Asynchronously

Add the `Async` attribute alongside `Scheduled` to dispatch a detection to Laravel's queue instead of running it in the scheduler process:

```php
use DataHealth\Attributes\Async;
use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

#[Async(queue: 'data-health', connection: 'redis')]
#[Scheduled('*/5 * * * *')]
class DuplicateCustomerFinding extends Finding implements CanDetect
{
    public static function detect(): int
    {
        // Detect and record findings...

        return 0;
    }
}
```

Both arguments are optional. When omitted, Laravel uses the application's default queue and connection. Configure a non-`sync` queue connection and run a queue worker to process detections asynchronously. Only one queued or running detection job is allowed per Finding class at a time, using Laravel's unique-job cache lock.

### Assigning Findings to Worklists

Add the `Worklist` attribute to a finding to save its worklist on newly created finding records:

```php
use DataHealth\Attributes\Worklist;
use DataHealth\Finding;

#[Worklist('data-quality')]
class DuplicateCustomerFinding extends Finding
{
    // ...
}
```

Findings without the attribute are saved without a worklist.

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
