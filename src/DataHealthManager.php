<?php

declare(strict_types=1);

namespace DataHealth;

use DataHealth\Contracts\CanResolve;
use DataHealth\Contracts\CanVerify;
use DataHealth\Contracts\Resolver;
use DataHealth\Contracts\Verifier;
use DataHealth\Enums\FindingUrgency;
use DataHealth\Enums\RecordStatus;
use DataHealth\Models\FindingRecord;
use RuntimeException;

class DataHealthManager
{
    public function __construct(
        private FindingRegistry $registry,
        private ContextHasher $contextHasher,
    ) {}

    public function registry(): FindingRegistry
    {
        return $this->registry;
    }

    public function getFindingForRecord(FindingRecord $record): Finding
    {
        $class = $this->registry->getClass($record->key);

        return new $class($record->model, $record->context);
    }

    public function found(Finding $finding): FindingRecord
    {
        $context = $finding->buildContext();
        $detectedAt = now();

        $record = FindingRecord::query()->firstOrCreate(
            [
                'key' => $finding::key(),
                'model_type' => $finding->model->getMorphClass(),
                'model_id' => $finding->model->getKey(),
                'context_hash' => $this->contextHasher->hash($context),
            ],
            [
                'status' => RecordStatus::Active,
                'context' => $context,
                'worklist' => $finding::getWorklist(),
                'urgency' => $finding::getUrgency() ?? FindingUrgency::NORMAL,
                'last_detected_at' => $detectedAt,
            ],
        );

        if ($record->wasRecentlyCreated) {
            if ($finding->isAutomaticallyResolved()) {
                $this->resolve($record);
            }

            return $record;
        }

        if ($record->status === RecordStatus::Resolved) {
            $record->status = RecordStatus::Active;
        }

        return tap($record)->update([
            'last_detected_at' => $detectedAt,
        ]);
    }

    public function resolve(FindingRecord $record): bool
    {
        $finding = $this->getFindingForRecord($record);

        if (! $finding instanceof CanResolve) {
            throw new RuntimeException($finding::class.' does not implement CanResolve');
        }

        $response = $finding->resolve();

        $result = match (true) {
            is_bool($response) => $response,
            is_callable($response) => app()->call($response),
            is_a($response, Resolver::class, true) => app()->make($response)->resolve($record),
            default => throw new RuntimeException($finding::class.' does not implement Resolver'),
        };

        if ($result === true) {
            $record->update(['status' => RecordStatus::Resolved]);
        }

        return $result;
    }

    public function verify(FindingRecord $record): bool
    {
        $finding = $this->getFindingForRecord($record);

        if (! $finding instanceof CanVerify) {
            throw new RuntimeException($finding::class.' does not implement CanVerify');
        }

        $response = $finding->verify();

        $result = match (true) {
            is_bool($response) => $response,
            is_callable($response) => app()->call($response),
            is_a($response, Verifier::class, true) => app()->make($response)->verify($record),
            default => throw new RuntimeException($finding::class.' does not implement Verifier'),
        };

        if ($result === true) {
            $record->update(['status' => RecordStatus::Resolved]);
        }

        return $result;
    }
}
