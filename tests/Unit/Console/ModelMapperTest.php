<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit\Console;

use Illuminate\Support\Facades\Schema;
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
            'published' => 'boolean',
            'rating' => 'integer',
        ];

        foreach ($expected as $key => $value) {
            self::assertArrayHasKey($key, $props);
            self::assertEquals($value, $props[$key]);
        }
    }

    public function test_get_declared_properties_maps_postgres_column_types(): void
    {
        // Arrange: SQLite cannot produce these, so stand in for Postgres, which reports
        // the full definition on request and its own bare type name otherwise.
        $columns = [
            'id'           => ['uuid', 'uuid'],
            'meta'         => ['jsonb', 'jsonb'],
            'score'        => ['real', 'float4'],
            'weight'       => ['double precision', 'float8'],
            'published_at' => ['timestamp(0) with time zone', 'timestamptz'],
        ];
        Schema::shouldReceive('getColumnListing')->with('posts')->andReturn(array_keys($columns));
        foreach ($columns as $column => [$definition, $type]) {
            Schema::shouldReceive('getColumnType')->with('posts', $column, true)->andReturn($definition);
            Schema::shouldReceive('getColumnType')->with('posts', $column)->andReturn($type);
        }

        // Act
        $props = (new ModelMapper(new Post()))->getDeclaredProperties();

        // Assert
        self::assertSame('string', $props['id']);
        self::assertSame('array', $props['meta']);
        self::assertSame('float', $props['score']);
        self::assertSame('float', $props['weight']);
        self::assertSame('datetime', $props['published_at']);
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
