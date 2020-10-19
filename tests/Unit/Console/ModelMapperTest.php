<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Console;

use Rexlabs\Laravel\Smokescreen\Console\ModelMapper;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\Post;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;
use Rexlabs\Laravel\Smokescreen\Tests\UsesModelStubs;

class ModelMapperTest extends TestCase
{
    use UsesModelStubs;

    public function test_get_includes(): void
    {
        $this->createSchemas();
        $this->createModels();

        $modelInspector = new ModelMapper(Post::find(1));
        $this->assertEquals(
            [
            'user'     => 'relation|item',
            'comments' => 'relation|collection',
            ],
            $modelInspector->getIncludes()
        );
    }

    public function test_get_declared_properties(): void
    {
        $this->createSchemas();
        $this->createModels();

        $modelInspector = new ModelMapper(Post::find(1));
        $props = $modelInspector->getDeclaredProperties();
        $expected = [

            'id' => 'integer',
            'title' => 'string',
            'body' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'origin' => 'string',
        ];

        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $props);
            self::assertEquals($value, $props[$key]);
        }
    }

    public function test_get_default_properties(): void
    {
        $this->createSchemas();
        $this->createModels();

        $modelInspector = new ModelMapper(Post::find(1));
        $props = $modelInspector->getDefaultProperties();
        $this->assertEquals([], $props);
    }
}
