<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\Key;
use DataHealth\Finding;

#[Key('duplicate-customer')]
class KeyedFinding extends Finding {}
