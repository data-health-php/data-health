<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use DataHealth\Console\Concerns\InteractsWithTerminal;
use DataHealth\Console\Prompts\RecordTablePrompt;
use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Enums\RecordStatus;
use DataHealth\FindingRegistry;
use DataHealth\Models\FindingRecord;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Laravel\Prompts\MultiSelectPrompt;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Termwind\parse;

class RecordsCommand extends Command
{
    use InteractsWithTerminal;

    /** @var string */
    protected $signature = 'health:records
        {--status=active : Filter by active, ignored, resolved, or all}
        {--finding= : Filter by a finding key or fully qualified class name}
        {--page=1 : Page to display}
        {--per-page=20 : Records per page (1-100)}
        {--interactive : Force the interactive browser (automatic in a terminal; -n disables it)}';

    /** @var string */
    protected $description = 'List and interactively manage Data Health finding records';

    public function handle(FindingRegistry $registry): int
    {
        try {
            $interactive = $this->interactiveMode();

            $page = $this->positiveIntegerOption('page');
            $perPage = $this->positiveIntegerOption('per-page', 100);
            $query = $this->recordQuery($registry);
            $failed = false;
            $fallback = false;

            $this->line(parse('<div class="my-1"><span class="bg-cyan-600 px-1 font-bold text-white">Data Health</span> Finding records</div>'));

            while (true) {
                $total = (clone $query)->count();

                if ($total === 0) {
                    $this->info('No finding records match these filters.');

                    return $failed ? self::FAILURE : self::SUCCESS;
                }

                $lastPage = (int) ceil($total / $perPage);
                $page = min($page, $lastPage);
                $records = (clone $query)->orderBy('id')->forPage($page, $perPage)->get();
                $label = "Page {$page} of {$lastPage} ({$total} records).";

                if (! $interactive) {
                    $this->displayRecords($records, $registry);
                    $this->line($label);

                    return self::SUCCESS;
                }

                $selected = null;

                if (! $fallback && $this->useInteractiveTable()) {
                    $rows = $records->mapWithKeys(fn (FindingRecord $record): array => [
                        $record->id => [
                            (string) $record->id, $record->key, $record->model_type.':'.$record->model_id,
                            $record->status->value, $record->urgency->value,
                            implode(', ', $this->availableActions($record, $registry)) ?: '-',
                        ],
                    ])->all();
                    $prompt = new RecordTablePrompt($rows, $label, $page < $lastPage, $page > 1);
                    $selected = $prompt->prompt();
                    $action = $prompt->action;

                    if ($action === 'menu') {
                        $fallback = true;

                        continue;
                    }
                } else {
                    $this->displayRecords($records, $registry);
                    $this->line($label);
                    $action = $this->menuAction($page, $lastPage);
                }

                if ($action === 'exit') {
                    return $failed ? self::FAILURE : self::SUCCESS;
                }

                if ($action === 'next') {
                    $page++;
                } elseif ($action === 'previous') {
                    $page--;
                } elseif ($action === 'inspect') {
                    $this->inspectRecord($records, $selected[0] ?? null);
                } elseif (in_array($action, ['verify', 'resolve', 'ignore', 'solve'], true)) {
                    $succeeded = $this->runAction($action, $records, $registry, $selected);
                    $failed = ! $succeeded || $failed;
                }
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function useInteractiveTable(): bool
    {
        return ! MultiSelectPrompt::shouldFallback();
    }

    private function menuAction(int $page, int $lastPage): string
    {
        $options = [
            'inspect' => 'Inspect a record',
            'verify' => 'Verify records',
            'resolve' => 'Resolve records',
            'ignore' => 'Ignore records',
            'solve' => 'Mark records as solved',
        ];

        if ($page < $lastPage) {
            $options['next'] = 'Next page';
        }

        if ($page > 1) {
            $options['previous'] = 'Previous page';
        }

        $options['refresh'] = 'Refresh records';
        $options['exit'] = 'Exit';

        return (string) select('Record actions', $options, default: 'exit');
    }

    /** @return Builder<FindingRecord> */
    private function recordQuery(FindingRegistry $registry): Builder
    {
        $status = $this->option('status');

        if (! is_string($status) || ($status !== 'all' && RecordStatus::tryFrom($status) === null)) {
            throw new InvalidArgumentException('--status must be active, ignored, resolved, or all.');
        }

        $query = FindingRecord::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $finding = $this->option('finding');

        if ($finding !== null) {
            if (trim($finding) === '') {
                throw new InvalidArgumentException('--finding requires a finding key or class name.');
            }

            $query->where('key', $registry->getKey($finding));
        }

        return $query;
    }

    private function positiveIntegerOption(string $name, ?int $max = null): int
    {
        $value = filter_var($this->option($name), FILTER_VALIDATE_INT);

        if ($value === false || $value < 1 || ($max !== null && $value > $max)) {
            throw new InvalidArgumentException($max === null
                ? '--'.$name.' must be a positive integer.'
                : '--'.$name.' must be between 1 and '.$max.'.');
        }

        return $value;
    }

    /** @param Collection<int, FindingRecord> $records */
    private function displayRecords(Collection $records, FindingRegistry $registry): void
    {
        $this->table(['ID', 'Finding', 'Model', 'Status', 'Urgency', 'Actions'], $records->map(fn (FindingRecord $record): array => [
            $record->id,
            OutputFormatter::escape($record->key),
            OutputFormatter::escape($record->model_type.':'.$record->model_id),
            $record->status->value,
            $record->urgency->value,
            implode(', ', $this->availableActions($record, $registry)) ?: '-',
        ])->all());
    }

    /** @return list<string> */
    private function availableActions(FindingRecord $record, FindingRegistry $registry): array
    {
        $actions = match ($record->status) {
            RecordStatus::Active => ['ignore', 'solve'],
            RecordStatus::Ignored => ['solve'],
            RecordStatus::Resolved => [],
        };

        try {
            $class = $registry->getClass($record->key);
        } catch (Throwable) {
            return $actions;
        }

        if (is_a($class, CanVerify::class, true)) {
            $actions[] = 'verify';
        }

        if (is_a($class, CanResolve::class, true)) {
            $actions[] = 'resolve';
        }

        return $actions;
    }

    /**
     * @param  Collection<int, FindingRecord>  $records
     * @return array<int, string>
     */
    private function recordOptions(Collection $records): array
    {
        return $records->mapWithKeys(fn (FindingRecord $record): array => [
            $record->id => '#'.$record->id.' '.$record->key.' ('.$record->model_type.':'.$record->model_id.')',
        ])->all();
    }

    /** @param Collection<int, FindingRecord> $records */
    private function inspectRecord(Collection $records, int|string|null $id = null): void
    {
        $id ??= select('Select a record to inspect', $this->recordOptions($records));
        $record = $records->firstWhere('id', $id);

        if ($record !== null) {
            $this->line(OutputFormatter::escape(json_encode([
                'id' => $record->id,
                'key' => $record->key,
                'model_type' => $record->model_type,
                'model_id' => $record->model_id,
                'status' => $record->status->value,
                'urgency' => $record->urgency->value,
                'worklist' => $record->worklist,
                'context' => $record->context,
                'last_detected_at' => $record->last_detected_at->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
        }
    }

    /**
     * @param  Collection<int, FindingRecord>  $records
     * @param  array<int|string>|null  $selected
     */
    private function runAction(string $action, Collection $records, FindingRegistry $registry, ?array $selected = null): bool
    {
        if ($selected !== null) {
            $records = $records->whereIn('id', $selected);

            foreach ($records as $record) {
                if (! in_array($action, $this->availableActions($record, $registry), true)) {
                    $this->warn("Skipped record {$record->id}: {$action} is not supported.");
                }
            }
        }

        $eligible = $records->filter(fn (FindingRecord $record): bool => in_array($action, $this->availableActions($record, $registry), true));

        if ($eligible->isEmpty()) {
            $this->info("No records on this page support {$action}.");

            return true;
        }

        $question = $action === 'solve' ? 'Select records to mark as solved' : 'Select records to '.$action;
        $selected = $selected === null
            ? multiselect($question, $this->recordOptions($eligible))
            : $eligible->pluck('id')->all();

        if ($selected === []) {
            return true;
        }

        $completed = 0;
        $failed = 0;

        foreach ($selected as $id) {
            $result = match ($action) {
                'ignore' => $this->updateRecordStatus((string) $id, RecordStatus::Ignored),
                'solve' => $this->updateRecordStatus((string) $id, RecordStatus::Resolved),
                default => $this->call($action, ['id' => (string) $id]),
            };

            if ($result === self::SUCCESS) {
                $completed++;
            } else {
                $failed++;
            }
        }

        $this->line("Action summary: {$completed} succeeded, {$failed} failed.");

        return $failed === 0;
    }

    private function updateRecordStatus(string $id, RecordStatus $status): int
    {
        $outcome = $status === RecordStatus::Ignored ? 'ignored' : 'marked as solved';

        try {
            $record = FindingRecord::query()->find($id);

            if ($record === null) {
                $this->error("Finding record {$id} was not found.");

                return self::FAILURE;
            }

            if ($record->status === $status || ($status === RecordStatus::Ignored && $record->status !== RecordStatus::Active)) {
                $this->error("Finding record {$id} no longer supports this status change.");

                return self::FAILURE;
            }

            if (! $record->update(['status' => $status])) {
                $this->error("Finding record {$id} could not be {$outcome}.");

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Finding record {$id} {$outcome}.");

        return self::SUCCESS;
    }
}
