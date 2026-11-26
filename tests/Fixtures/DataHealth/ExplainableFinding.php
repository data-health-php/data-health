<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\Description;
use DataHealth\Contracts\CanExplain;
use DataHealth\Finding;
use RuntimeException;

#[Description('The model name should be Correct.')]
class ExplainableFinding extends Finding implements CanExplain
{
    public function explain(): array
    {
        if (($this->context['fail'] ?? false) === true) {
            throw new RuntimeException('Explanation is unavailable.');
        }

        $model = $this->model->refresh();

        return [
            'expected' => 'The name should be Correct.',
            'actual' => ['name' => $model->getAttribute('name')],
            'still_applies' => $model->getAttribute('name') !== 'Correct',
            'observed_at' => now()->toIso8601String(),
        ];
    }
}
