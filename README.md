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

## Usage

<!-- Add a basic usage example here. -->

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
