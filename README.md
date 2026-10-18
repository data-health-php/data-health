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
queued detection, synchronous verification and resolution, urgency and worklist metadata,
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
| `--async` | Add `#[Async]` to `detect()` and add detection if needed; no schedule required. |
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

## Interactive Console

Open the Data Health suite to detect findings or browse and act on records:

```bash
php artisan health
```

The menu returns after each operation. It uses Termwind for terminal styling and
Laravel Prompts for selection, with Laravel's fallback prompts on Windows.

Browse active finding records in a terminal, or print one page without prompting:

```bash
php artisan health:records
php artisan health:records --no-interaction
php artisan health:records --status=all --finding=DuplicateCustomerFinding --per-page=10
```

The list shows record IDs, finding keys, models, statuses, urgency, and available
actions. In interactive mode, navigate the table with the arrow keys and toggle
rows with Space. Press `v` to verify, `r` to run the resolver, `i` to ignore, or `s`
to mark checked records as solved. With nothing checked, the action applies to the
highlighted row. Unsupported actions are
skipped with a message. Enter inspects the highlighted record's context, worklist,
and detection time. Actions run synchronously,
report individual results, and refresh the list. Failures do not prevent the
remaining selected records from running; any failure makes the browser and suite
exit with code `1` when closed.

Ignoring marks active records `ignored` without running verification or resolution
logic or changing the affected model. It also works when the finding class is no
longer registered. Already ignored or resolved records are skipped.

Marking as solved sets an active or ignored record to `resolved` without running
verification or resolution logic or changing the affected model. It requires no
finding contract and works with unregistered finding keys. Already resolved
records are skipped.

The browser defaults to active records, 20 per page. Use `--status=active|ignored|resolved|all`,
`--finding=key-or-class`, `--page=1`, and `--per-page=20` (1–100) to narrow the list.
Press `a` to toggle all rows on the page, `n`/`p` for next/previous pages, `f` to
refresh, or `q` to exit. Successful actions remove resolved records from an active-only
list; ignored records leave that list too. Selection resets after an action, inspection, refresh, or page change. Narrow
terminals hide secondary columns and truncate long values; inspection shows full values.

Where raw keyboard input is unavailable, including Laravel's Windows fallback,
the browser uses menus: choose **Verify records**, **Resolve records**, **Ignore records**,
or **Mark records as solved**, then select
supported records. An empty selection in this fallback runs no actions.

Detection and record browsing are interactive by default when both standard input
and standard output are attached to a terminal. Use `--no-interaction` (`-n`) to
run detection without selection or print a records page and exit. Piped and
redirected runs are non-interactive automatically. `--interactive` remains
available to request interaction explicitly and cannot be combined with `-n`.
The `health` suite menu requires interaction and rejects `--no-interaction`.

## Running Detection

Run detection for discovered findings implementing `DataHealth\Contracts\CanDetect`:

```bash
php artisan health:detect
```

In a terminal, this opens the finding selector. Use `php artisan health:detect -n`
to run all candidates immediately. Outside a terminal, detection runs without prompting.

Detection honors `#[Async]` on `detect()` and otherwise runs
synchronously, including findings without `#[Scheduled]`. Manual runs ignore cron
timing and `data-health.scheduler.enabled`. Use `--sync` to force synchronous
detection or `--async` to queue every selected finding. These flags cannot be combined.

| Parameter | Behavior |
| --- | --- |
| `findings...` | Optional finding keys or fully qualified class names. Omit to include all detectable findings as candidates. |
| `--except=...` | Exclude a finding by key or class; repeat for multiple exclusions. |
| `--interactive` | Explicitly request finding selection, which is automatic in a terminal. |
| `--no-interaction`, `-n` | Run the validated candidates without prompting. |
| `--no-progress` | Hide the progress bar, for example in scripted runs. |
| `--async` | Dispatch one unique `DetectFinding` job per selected finding, regardless of attributes. |
| `--sync` | Run all selected detections synchronously, ignoring `#[Async]`. |
| `--queue=`, `--connection=` | Override queue routing; require `--async` and non-empty values. |

```bash
php artisan health:detect DuplicateCustomerFinding MissingInvoiceFinding
php artisan health:detect --except=ExpensiveFinding
php artisan health:detect --async --queue=data-health
php artisan health:detect --interactive
php artisan health:detect --interactive --async --except=ExpensiveFinding
```

The progress bar tracks selected finding classes, including completed, skipped,
and failed runs, and displays the current finding. For `--async`, progress tracks
dispatch rather than background job completion. Interactive selection starts with
nothing selected; use Space to toggle findings and Enter to run them. Selecting
nothing exits successfully without detection. `--interactive` requires interaction
and cannot be combined with `--no-interaction`.

Async runs use the queue and connection from `#[Async]` on `detect()` when present;
omitted values use the application's queue defaults. Command-line overrides take precedence
independently. Configure a non-`sync` connection and a running queue worker for
background execution. Jobs already queued or running for the same finding are
skipped using Laravel's unique-job lock.

All selectors, including exclusions, must identify registered detectable findings
and are validated before execution begins. Duplicate selections run once, and
exclusions take precedence. An empty selection succeeds without running detection.

The command reports each finding and a summary of completed or queued detections,
skips, and failures. Individual failures do not stop the remaining findings; the
command returns a nonzero exit code if any detection or dispatch fails. Async
success confirms dispatch, with results handled by the worker. Detection methods may
return an integer, `null`, a callable, or a class string implementing
`DataHealth\Contracts\Detector`. Returned callables are invoked through Laravel's
container, and detector classes are resolved through the container before their
instance `detect(): ?int` method runs. Constructor injection is supported. This works
for manual, scheduled, and queued detection.

Move detection into a dedicated class by returning its class name from the finding:

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

Detector classes return an optional count and report matches with the finding's
`found()` method. A class string that does not implement `Detector` causes detection
to fail.

## Verifying and Resolving Findings

Run an action for a persisted finding record by its ID:

```bash
php artisan verify 1
php artisan resolve 1
```

The namespaced aliases `health:verify 1` and `health:resolve 1` provide the same behavior.
Use `health:records` in a terminal to select multiple records without typing IDs.

The finding must implement `CanVerify` or `CanResolve` for the requested action.
The commands use the manager to evaluate the method's return value: a boolean,
a callable invoked through Laravel's container, or a `Verifier` / `Resolver`
handler class resolved through the container.

Verification and resolution always run synchronously and return immediate results.
A `true` result marks the record resolved, reports success, and exits with code `0`.
A `false` result leaves its status unchanged and exits with code `1`. Missing
records, unsupported actions, and exceptions also report an error and exit with
code `1`. The ID identifies the finding record, rather than its related model.

## Asynchronous Detection

Apply `DataHealth\Attributes\Async` only to a finding's `detect()` method:

```php
use DataHealth\Attributes\Async;
use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;

class MissingEmailFinding extends Finding implements CanDetect
{
    #[Async(queue: 'data-health', connection: 'redis')]
    public static function detect(): string
    {
        return MissingEmailDetector::class;
    }
}
```

This uses the detector from the example above. The attribute queues detection
through `health:detect` and scheduled detection. Direct PHP calls such as
`MyFinding::detect()` remain synchronous. Add `#[Scheduled]` to the class when
periodic detection is wanted; `#[Async]` alone does not schedule execution.

Class-level `#[Async]` and attributes on other methods are rejected during finding
discovery. Move existing class-level attributes onto `detect()`. The generator's
`--async` option now adds the method attribute and detection capability without
requiring a schedule.

`DataHealth::verify($record)` and `DataHealth::resolve($record)` return `bool`.
Their commands and record-browser actions provide immediate success or failure;
there are no async execution flags for these actions. `#[AutoResolve]` also runs
resolution immediately. `#[AutoVerify]` remains separate automation that queues
verification after commit, preserves its delay and active-record check, and uses
application queue defaults. Detection's `#[Async]` does not configure it.

Use a non-`sync` queue connection and run its worker for background detection.
Laravel's `sync` connection executes dispatched jobs immediately.

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
