<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\CollidingFindings\Explicit\First;

use DataHealth\Attributes\Key;
use DataHealth\Finding;

#[Key('shared-key')]
class FirstFinding extends Finding {}
