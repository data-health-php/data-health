<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\AutoVerify;

#[AutoVerify]
class AutomaticallyVerifiedFinding extends ActionableFinding {}
