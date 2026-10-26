---
name: data-health-development
description: >
  Design, generate, test, and maintain Data Health findings in Laravel applications.
  Use when scanning application code for data inconsistencies, implementing detection,
  verification or resolution, or configuring data-health/data-health.
license: MIT
metadata:
  author: Jascha Gerles
---

# Data Health

## Primary Goal

Express established application rules as simple findings through the public API of
`data-health/data-health`. The application defines unhealthy data and corrections;
the package persists, deduplicates, and tracks each occurrence.

## Workflow

### 1. Establish the rule before implementing a finding

For each proposed finding, establish:

- **Invariant:** the concrete application rule and which records it applies to.
- **Evidence:** application actions, services, validation, queries, tests, or documented
  requirements that support the rule. Field names and schema nullability alone are insufficient.
- **Exceptions:** valid states, historical records, imports, optional relationships,
  tenant boundaries, or other cases the rule must allow.
- **Occurrence identity:** the affected model and stable context needed to distinguish it.
- **Correction:** the known application action that can safely restore the invariant,
  or why the outcome requires human judgment.

If evidence is insufficient, report the candidate and the missing business decision
instead of implementing a speculative rule or correction.

### 2. Keep the implementation focused

- Start with a class extending `DataHealth\Finding` directly. Keep detection,
  verification, and resolution together unless existing application services own the logic.
- Generate it with `php artisan health:make:finding Name --verify --resolve`, selecting
  only the capabilities justified by the rule. `CanDetect` is the default.
- Replace all generated placeholders; unfinished methods throw `LogicException`.
- `detect(): null` searches candidates and calls `self::found($model, $context)` for
  each occurrence. Leave business-data correction to resolution.
- `verify(): bool` rechecks this occurrence using `$this->model`, `$this->context`,
  and relevant related data. Keep business data unchanged; do not rerun detection.
  A shared read-only query or predicate is appropriate when it avoids divergent rules.
- Assess resolution for every finding. Implement `CanResolve` when application rules
  establish a deterministic correction; otherwise explain the judgment needed and omit it.
- `resolve(): bool` rechecks current applicability and uses existing domain actions or
  services when available. Preserve valid state changes and make retries safe; use
  transactions and row locks where concurrent local updates require them.
- Verification returns `true` when the occurrence no longer applies. Resolution returns
  `true` when corrected or already gone. Through the manager/facade, `true` marks the
  record resolved and `false` leaves its status unchanged.
- Throw for unexpected model types, malformed required context, or an unreliable check.
  Do not turn invalid input or infrastructure failures into successful verification.
- Implementing `resolve()` exposes an action; it does not execute it automatically.
  `#[AutoResolve]` separately opts into immediate resolution on first record creation.
  Rediscovery and reopening do not retry it. `#[AutoVerify]` independently queues
  verification on first creation, after commit, optionally delayed in minutes.
- Pass complete, stable, JSON-encodable context at construction or through `found()`.
  All context affects identity; avoid volatile diagnostic values and secrets.
- Introduce shared base classes, schema-wide reflection, or new service layers only
  when demonstrated application needs justify them.

### 3. Test observable behavior in the consuming application

Test unhealthy and healthy records, legitimate exceptions, repeat detection without
duplicates, and separate context occurrences. Verify one occurrence without reporting
new findings or changing business data. For a resolver, assert the correction and
record status, already-corrected or changed state, repeat execution, and failure paths.
Invalid model/context and unavailable dependencies must fail visibly without resolving
the record. Use the application's existing test setup and mock external systems.

### 4. Configure and run only the required operations

Read [commands and configuration](references/commands.md) for installation, discovery,
generation options, scheduling, queues, context, metadata, record actions, and cleanup.
Use `php artisan health:detect Name -n` for scripted detection and
`php artisan health` for interactive review. Use package entry points for status updates:
`DataHealth::verify($record)` and `DataHealth::resolve($record)`; direct method calls
only run application logic. Capabilities are independent, and application code may
report `Name::found($model)` without implementing detection.

Inline `null` and boolean results are the ordinary case. Callables and dedicated
`Detector`, `Verifier`, and `Resolver` handlers are supported for justified integration
needs; see the contract reference below. Core detection ignores integer counts;
Filament manual detection displays them. Filament manual detection supports callables
but currently does not resolve `Detector` class strings or honor `Async` routing.

## Example

Assume application rules establish that an order with `paid_at` set and `status` equal
to `pending` should be `paid`, and that this application uses string statuses. This
example needs no context because the problem occurs at most once per order. Reuse an
existing domain action if it already owns this transition.

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

## Rules, References, and Templates

Read the consuming application's conventions and `config/data-health.php` before
execution, then consult only the relevant references:

- Package `README.md` for the lifecycle and installation.
- [Design a finding from application evidence](https://data-health-php.github.io/data-health-docs/guides/detecting-problems/#establish-the-application-rule).
- [Quick start](https://data-health-php.github.io/data-health-docs/start-here/quick-start/).
- [Context](https://data-health-php.github.io/data-health-docs/guides/context/) for identity and validation.
- [Contracts and delegation](https://data-health-php.github.io/data-health-docs/reference/attributes-and-contracts/).
- [Safe resolution](https://data-health-php.github.io/data-health-docs/guides/resolving-findings/#update-data-safely).
- [CLI reference](https://data-health-php.github.io/data-health-docs/cli/overview/).

## Anti-patterns

- Inferring unhealthy data from column names or nullability without application evidence.
- Treating historical exceptions as corruption or guessing how ambiguous records should be repaired.
- Rerunning detection from verification, correcting business data in verification, or
  returning `true` because model/context validation failed.
- Overwriting a valid state that changed after detection or duplicating domain correction logic.
- Adding `AutoResolve`, schedules, queues, or shared abstractions without a demonstrated need.
- Changing persisted finding keys or context shape without planning existing-record migration.
- Assuming missing scan results resolve old records, or rediscovery repeats automatic actions.
