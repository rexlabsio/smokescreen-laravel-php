<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Console;

use Rexlabs\Laravel\Smokescreen\Console\ModelMapper;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\Post;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;
use Rexlabs\Laravel\Smokescreen\Tests\UsesModelStubs;

class ModelMapperTest extends TestCase
{
    use UsesModelStubs;

    public function test_get_includes()
    {
        $this->createSchemas();
        $this->createModels();

        $modelInspector = new ModelMapper(Post::find(1));
        $this->assertEquals([
            'user'     => 'relation|item',
            'comments' => 'relation|collection',
        ], $modelInspector->getIncludes());
    }

    public function test_get_declared_properties()
    {
        $this->createSchemas();
        $this->createModels();

        $modelInspector = new ModelMapper(Post::find(1));
        $props = $modelInspector->getDeclaredProperties();
        $this->assertArraySubset([
            'id' => 'integer',
        ], $props);
        $this->assertArraySubset([
            'title' => 'string',
        ], $props);
        $this->assertArraySubset([
            'body' => 'string',
        ], $props);
        $this->assertArraySubset([
            'created_at' => 'datetime',
        ], $props);
        $this->assertArraySubset([
            'updated_at' => 'datetime',
        ], $props);
        $this->assertArraySubset([
            'origin' => 'string',
        ], $props);
    }

    public function test_get_default_properties()
    {
        $this->createSchemas();
        $this->createModels();

        $modelInspector = new ModelMapper(Post::find(1));
        $props = $modelInspector->getDefaultProperties();
        $this->assertEquals([], $props);
    }

}
