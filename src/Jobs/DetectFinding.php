<?php

declare(strict_types=1);

namespace DataHealth\Jobs;

use DataHealth\Contracts\CanDetect;
use DataHealth\Finding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

class DetectFinding implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** @param class-string<Finding&CanDetect> $finding */
    public function __construct(public readonly string $finding) {}

    public function handle(): void
    {
        $this->finding::detect();
    }

    public function uniqueId(): string
    {
        return $this->finding;
    }
}
