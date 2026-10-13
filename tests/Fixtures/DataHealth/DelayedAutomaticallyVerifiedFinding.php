<?php

declare(strict_types=1);

namespace DataHealth\Tests\Fixtures\DataHealth;

use DataHealth\Attributes\AutoVerify;

#[AutoVerify(2)]
class DelayedAutomaticallyVerifiedFinding extends ActionableFinding {}
