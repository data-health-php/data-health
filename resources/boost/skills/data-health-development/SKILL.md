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

Generate a finding with `php artisan health:make:finding Customers/DuplicateCustomerFinding --detect --verify --resolve`.
The command writes to the first configured finding directory and namespace; use
`--directory=path/to/findings` to select another configured directory. It reports
the created path and refuses to overwrite an existing file unless `--force` is supplied.

- use `--detect`, `--verify`, and `--resolve` for the corresponding contracts and method placeholders
- configure `data-health.make_finding.interfaces` with a list of `DataHealth\Contracts\CanDetect::class`, `CanVerify::class`, and/or `CanResolve::class` to choose default contracts and method placeholders; the default is `[CanDetect::class]`
- set the list to `[]` for a plain finding; command options and automatic action attributes add their required capabilities to the configured defaults
- use `--key=`, `--description=`, `--worklist=`, and `--urgency=immediate|soon|normal|deferred` for class metadata
- use `--schedule="*/5 * * * *"` to add scheduled detection; `--async` adds `Async` to `detect()` and adds detection capability if needed, without requiring a schedule; optional `--queue=` / `--connection=` set routing
- use `--auto-resolve` to add automatic resolution and its contract, or `--auto-verify=0` to add automatic verification with a non-negative delay in minutes and its contract
- implement the generated method placeholders: detection returns `null`, and verification and resolution return `false`
- inspect all options with `php artisan help health:make:finding`

Run detection manually with `php artisan health:detect`:

- runs all discovered `CanDetect` findings, honoring `Async` on `detect()`; ignores cron timing and `data-health.scheduler.enabled`; unannotated detection is synchronous
- pass optional finding keys or fully qualified class names to select findings; repeat `--except=key-or-class` to exclude findings
- selectors and exclusions must identify registered detectable findings; all are validated before execution, duplicate selections run once, and exclusions take precedence
- detection defaults to finding selection when standard input and output are terminals; `-n` / `--no-interaction` runs candidates without prompting, and piped or redirected runs are non-interactive automatically
- `--interactive` explicitly requests selection after exclusions; an empty selection runs nothing, and combining this flag with `--no-interaction` is rejected
- the progress bar tracks finding classes, including failures and skips, and shows the current finding; use `--no-progress` to hide it
- use `--sync` to force synchronous detection or `--async` to force queuing; these flags cannot be combined; queued detection dispatches one unique `DetectFinding` job per selected finding; pending or running detections are skipped
- async routing uses `Async` on `detect()` or application defaults; `--queue=` and `--connection=` independently override routing and require `--async` and non-empty values
- detection and dispatch failures are reported while remaining findings continue; the summary reports completed or queued, skipped, and failed detections, and any failure returns a nonzero exit code
- async success confirms dispatch; workers handle detection results, and a non-`sync` connection is required for background execution
- detection may return an integer, `null`, a callable, or a class string implementing `DataHealth\Contracts\Detector`
- manual, scheduled, and queued detections invoke returned callables through Laravel's container and resolve detector classes through the container, including constructor dependencies
- implement an instance `detect(): ?int` method on detector classes, report matches with the finding's `found()` method, and return an optional count; invalid detector classes fail detection
- an empty selection succeeds without running detection; inspect all options with `php artisan help health:detect`

### 3. Configure scheduled detections

- scheduled detections are enabled by default
- set `DATA_HEALTH_SCHEDULER_ENABLED=false` to prevent the package from registering scheduled detection callbacks
- use `data-health.scheduler.enabled` when overriding the value at runtime or in tests
- scheduled detections do not overlap and run on one scheduler server per cron occurrence
- use a shared `database`, `memcached`, `dynamodb`, or `redis` cache store when the scheduler runs on multiple servers

### 4. Configure asynchronous detection

- add `#[DataHealth\Attributes\Async]` only to the finding's `detect()` method to queue manual and scheduled detection
- class-level usage and attributes on other methods are rejected during finding discovery; move existing class attributes onto `detect()`
- optionally pass `queue` and `connection`; omitted values use application defaults
- direct PHP calls to `detect()` remain synchronous; use package detection entry points to honor attributes
- configure a non-`sync` connection and operate its queue worker for background execution
- keep a lock-capable shared cache configured so only one queued or running detection exists per finding class
- verification and resolution always return their actual boolean results synchronously; `Async` does not configure automatic verification

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

### 8. Add finding context

- pass context as the second argument when constructing a finding or calling its static `found` method
- use context for data that identifies or explains a finding for its related model
- read the constructor-provided data from the finding's readonly `context` property in verification and resolution logic
- remember that context contributes to finding identity, so equivalent associative arrays resolve to the same record while different values create distinct records

### 9. Browse, verify, and resolve findings

- open `php artisan health` for the interactive suite menu; detection opens a finding selector and records opens the interactive browser
- `php artisan health:records` opens the record browser by default when standard input and output are terminals; `-n` / `--no-interaction` prints a page and exits, and piped or redirected runs also do not prompt
- filter with `--status=active|ignored|resolved|all` and `--finding=key-or-class`; pagination defaults to `--page=1 --per-page=20`, with 1–100 records per page
- in the interactive table, move with arrows and check rows with Space; `v` verifies and `r` resolves checked rows, or the highlighted row if none are checked; unsupported actions are skipped
- `i` ignores checked active records, or the highlighted active record; it updates only record status, needs no finding contract, and works with unregistered finding keys
- ignored records disappear from the active list; already ignored or resolved records are skipped, and fallback menus include **Ignore records**
- `s` marks checked active or ignored records as solved, or the highlighted row if none are checked; it sets status to `resolved` without running action logic, requires no contract, and skips already resolved records
- the manual solved action also works with unregistered finding keys and is available as **Mark records as solved** in fallback menus; `r` continues to execute the resolver
- Enter inspects the highlighted record; `a` toggles all rows on the page, `n`/`p` changes pages, `f` refreshes, and `q` exits
- actions run synchronously and refresh the list; selection resets after actions, inspection, refresh, or page changes
- Laravel's fallback environments use menus and separate record selection; an empty fallback selection runs nothing
- record failures do not stop the batch; the browser and suite preserve a nonzero exit code after any failed action
- use `--no-interaction` for scripted detection or listing and `--no-progress` to hide detection progress; `health --no-interaction` is rejected
- run `php artisan verify 1` or `php artisan resolve 1` with the finding record ID
- `health:verify` and `health:resolve` are equivalent namespaced aliases
- implement `CanVerify` or `CanResolve` on the finding for the requested action
- actions run synchronously through the manager, which evaluates booleans, container-invoked callables, and container-resolved `Verifier` / `Resolver` handler classes
- manager/facade `verify($record): bool` and `resolve($record): bool` return immediate results; these commands have no `--async` / `--sync` flags
- automatic resolution is immediate; `AutoVerify` independently queues verification after commit with its configured delay, active-record guard, and application queue defaults
- a `true` result marks the record resolved and exits with code `0`; `false` leaves its status unchanged and exits with code `1`
- missing records, unsupported actions, and exceptions report an error and exit with code `1`

### 10. Clean up findings for deleted models

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

Run selected detections or queue all except an expensive finding:

```bash
php artisan health:detect DuplicateCustomerFinding MissingInvoiceFinding
php artisan health:detect --async --queue=data-health --except=ExpensiveFinding
```

Queue a scheduled detection:

```php
use DataHealth\Attributes\Async;
use DataHealth\Attributes\Scheduled;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

#[Scheduled('*/5 * * * *')]
class DuplicateCustomerFinding extends Finding implements CanDetect
{
    #[Async(queue: 'data-health', connection: 'redis')]
    public static function detect(): int|callable|string|null
    {
        return null;
    }
}
```

Delegate detection to a dedicated class:

```php
use App\Models\Customer;
use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\Detector;
use DataHealth\Finding;

class MissingEmailFinding extends Finding implements CanDetect
{
    public static function detect(): string
    {
        return MissingEmailDetector::class;
    }
}

class MissingEmailDetector implements Detector
{
    public function detect(): ?int
    {
        $customers = Customer::query()->whereNull('email')->get();

        foreach ($customers as $customer) {
            MissingEmailFinding::found($customer);
        }

        return $customers->count();
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
    public static function detect(): int|callable|string|null
    {
        return null;
    }
}
```

Record context for a finding:

```php
DuplicateCustomerFinding::found($customer, [
    'duplicate_customer_id' => $duplicateCustomer->getKey(),
]);
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
- do not add `AutoVerify` to a finding that does not implement `CanVerify`
- do not rely on generated detection, verification, or resolution placeholders as completed application logic
- do not expect automatic verification to run again when an existing record is rediscovered
- do not rename an explicit finding key after records have been persisted unless those records are migrated too
- do not override or derive finding context after construction; pass the complete context to the constructor or static `found` method
- do not use method descriptions as execution logic; they are optional user-facing metadata
- do not use mass deletes for models with finding records because Eloquent does not dispatch model events for them
