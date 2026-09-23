<?php

declare(strict_types=1);

namespace DataHealth\Enums;

enum RecordStatus: string
{
    case Active = 'active';
    case Ignored = 'ignored';
    case Resolved = 'resolved';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
