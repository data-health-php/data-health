<?php

declare(strict_types=1);

namespace DataHealth\Enums;

enum FindingUrgency: string
{
    case IMMEDIATE = 'immediate';
    case SOON = 'soon';
    case NORMAL = 'normal';
    case DEFERRED = 'deferred';
}
