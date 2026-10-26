<?php

declare(strict_types=1);

namespace DataHealth\Jobs;

use DataHealth\Contracts\CanDetect;
use DataHealth\Contracts\Detector;
use DataHealth\Finding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

class DetectFinding implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** @param class-string<Finding&CanDetect> $finding */
    public function __construct(public readonly string $finding) {}

    public function handle(): void
    {
        $response = $this->finding::detect();

        match (true) {
            is_int($response), $response === null => null,
            is_callable($response) => app()->call($response),
            default => $this->detectUsingHandler($response),
        };
    }

    public function uniqueId(): string
    {
        return $this->finding;
    }

    private function detectUsingHandler(string $handler): void
    {
        if (! is_a($handler, Detector::class, true)) {
            throw new RuntimeException($this->finding.' does not implement Detector');
        }

        app()->make($handler)->detect();
    }
}
