<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Helpers;

use Rexlabs\Laravel\Smokescreen\Helpers\TransformHelper;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;

class TransformHelperTest extends TestCase
{
    public function test_when()
    {
        $class = new class() { use TransformHelper; };
        $this->assertEquals('true', $class->when(\is_int(1), 'true', 'false'));
        $this->assertEquals('false', $class->when(\is_string(1), 'true', 'false'));
    }
}