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
- place findings in any nested directory that follows the configured PSR-4 namespace structure
- only concrete classes extending `DataHealth\Finding` are registered; other PHP classes are ignored

### 3. Configure scheduled detections

- scheduled detections are enabled by default
- set `DATA_HEALTH_SCHEDULER_ENABLED=false` to prevent the package from registering scheduled detection callbacks
- use `data-health.scheduler.enabled` when overriding the value at runtime or in tests
- scheduled detections do not overlap and run on one scheduler server per cron occurrence
- use a shared `database`, `memcached`, `dynamodb`, or `redis` cache store when the scheduler runs on multiple servers

### 4. Configure asynchronous detections

- add `#[DataHealth\Attributes\Async]` alongside `#[DataHealth\Attributes\Scheduled(...)]` to dispatch a scheduled detection to Laravel's queue
- optionally pass `queue` and `connection` to the `Async` attribute; omit them to use the application's queue defaults
- configure a non-`sync` queue connection for actual asynchronous execution
- ensure a queue worker is running before enabling asynchronous detections
- keep a lock-capable shared cache configured so only one queued or running detection exists per finding class

### 5. Define stable finding keys

- add `#[DataHealth\Attributes\Key('stable-key')]` to a finding class when its persisted database key should not depend on the class basename
- omit the attribute to keep using the finding class basename as the database key

### 6. Assign findings to worklists

- add `#[DataHealth\Attributes\Worklist('worklist-name')]` to a finding class when newly created records should be assigned to that worklist
- omit the attribute when the finding should not have a worklist

### 7. Describe findings and actions

- add `#[DataHealth\Attributes\Description('...')]` to a finding class to explain what the finding means in a user interface
- add the same attribute to `detect`, `verify`, and `resolve` methods to explain what each operation does
- read class descriptions with `FindingClass::getDescription()`
- read action descriptions with `FindingClass::getMethodDescription('detect')`, substituting `verify` or `resolve` as needed
- handle `null` when the class, method, or method description is absent

### 8. Clean up findings for deleted models

- set `DATA_HEALTH_AUTO_DELETE_ENABLED=true` to register wildcard cleanup for every Eloquent model type
- leave `data-health.auto_delete.enabled` disabled when global cleanup is not wanted; no wildcard model listener is registered in that mode
- alternatively, add the `DataHealth\Concerns\HasFindingRecords` concern for cleanup limited to selected model types
- use the concern's `findingRecords` relationship when the application needs to query a model's findings
- delete models through Eloquent instances so the model event can delete their linked finding records
- remember that soft deletes also remove linked findings; run detection again after restoring a model

## Rules, References, and Templates

Read before executing:

- `config/data-health.php`
- package `README.md`

## Example

Queue a scheduled detection:

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
        return 0;
    }
}
```

Assign a finding to a worklist:

```php
use DataHealth\Attributes\Worklist;
use DataHealth\Finding;

#[Worklist('data-quality')]
class DuplicateCustomerFinding extends Finding
{
    // ...
}
```

Define a stable database key:

```php
use DataHealth\Attributes\Key;
use DataHealth\Finding;

#[Key('duplicate-customer')]
class DuplicateCustomerFinding extends Finding
{
    // ...
}
```

Describe a finding and its detection:

```php
use DataHealth\Attributes\Description;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

#[Description('The customer appears more than once with the same email address.')]
class DuplicateCustomerFinding extends Finding implements CanDetect
{
    #[Description('Searches customer records for duplicate email addresses.')]
    public static function detect(): int
    {
        return 0;
    }
}
```

Clean up linked finding records when a model is deleted:

```php
use DataHealth\Concerns\HasFindingRecords;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFindingRecords;
}
```

## Anti-patterns

- do not document package internals here; keep the skill focused on adoption in Laravel apps
- do not disable Laravel's scheduler globally when only Data Health detections need to be paused
- do not use per-server cache storage for scheduler locks in a multi-server deployment
- do not add `Async` without operating a worker for its selected queue and connection
- do not rename an explicit finding key after records have been persisted unless those records are migrated too
- do not use method descriptions as execution logic; they are optional user-facing metadata
- do not use mass deletes for models with finding records because Eloquent does not dispatch model events for them
