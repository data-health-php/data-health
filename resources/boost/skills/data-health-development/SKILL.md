---
name: data-health-development
description: >
  Configure and apply the Data Health package in Laravel applications.
license: MIT
metadata:
  author: Jascha Gerles
---

# Data Health

Use this skill when a Laravel application needs to integrate the Data Health package.

## Primary Goal

- apply the `23m/data-health` package's public API in the smallest correct way

## Workflow

### 1. Install and publish the package

- install `23m/data-health` with Composer
- publish migrations with `php artisan vendor:publish --tag="data-health-migrations"`
- run `php artisan migrate`
- publish configuration with `php artisan vendor:publish --tag="data-health-config"` when application-level overrides are needed

### 2. Configure detection discovery

- register finding directories and their namespaces in `config/data-health.php` under `directories`
- keep directory paths relative to the Laravel application's base path
- keep namespace prefixes terminated with a namespace separator

### 3. Configure scheduled detections

- scheduled detections are enabled by default
- set `DATA_HEALTH_SCHEDULER_ENABLED=false` to prevent the package from registering scheduled detection callbacks
- use `data-health.scheduler.enabled` when overriding the value at runtime or in tests

## Rules, References, and Templates

Read before executing:

- `config/data-health.php`
- package `README.md`

## Example

Disable scheduled detections in a test environment:

```php
config()->set('data-health.scheduler.enabled', false);
```

## Anti-patterns

- do not document package internals here; keep the skill focused on adoption in Laravel apps
- do not disable Laravel's scheduler globally when only Data Health detections need to be paused
