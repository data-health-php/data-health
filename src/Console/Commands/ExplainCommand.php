<?php

declare(strict_types=1);

namespace DataHealth\Console\Commands;

use DataHealth\DataHealthManager;
use DataHealth\Models\FindingRecord;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Throwable;

class ExplainCommand extends Command
{
    /** @var string */
    protected $signature = 'health:explain {id : The finding record ID}';

    /** @var string */
    protected $description = 'Explain the current facts of a Data Health finding without changing its status';

    public function handle(DataHealthManager $manager): int
    {
        /** @var string $id */
        $id = $this->argument('id');

        try {
            $record = FindingRecord::query()->find($id);

            if ($record === null) {
                $this->error("Finding record {$id} was not found.");

                return self::FAILURE;
            }

            $explanation = $manager->explain($record);
            $this->line(OutputFormatter::escape($record->getFinding()::getDescription() ?? $record->key));
            $this->line(OutputFormatter::escape(json_encode($explanation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)));
        } catch (Throwable $exception) {
            $this->error(OutputFormatter::escape($exception->getMessage()));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
