<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\CollidingFindings\Explicit\Second;

use DataHealth\Attributes\Key;
use DataHealth\Finding;

#[Key('shared-key')]
class SecondFinding extends Finding {}
