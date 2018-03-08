<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Rexlabs\Laravel\Smokescreen\Relations\RelationLoader;
use Rexlabs\Laravel\Smokescreen\Resources\CollectionResource;
use Rexlabs\Laravel\Smokescreen\Resources\ItemResource;
use Rexlabs\Laravel\Smokescreen\Smokescreen;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\Post;
use Rexlabs\Laravel\Smokescreen\Transformers\AbstractTransformer;
use Rexlabs\Smokescreen\Exception\MissingResourceException;
use Rexlabs\Smokescreen\Serializer\DefaultSerializer;

class SmokescreenTest extends \Rexlabs\Laravel\Smokescreen\Tests\TestCase
{
    public function test_transform_on_item_sets_item_resource()
    {
        $data = new class implements ItemResource
        {
        };
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['item'])
            ->getMock();
        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_assoc_array_sets_item_resource()
    {
        $data = [
            'name' => 'Bob',
            'age'  => 21,
        ];
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['item'])
            ->getMock();
        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_collection_sets_collection_resource()
    {
        $data = new class implements CollectionResource
        {
        };
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['collection'])
            ->getMock();
        $stub->expects($this->once())
            ->method('collection')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_sequential_array_sets_collection_resource()
    {
        $data = ['one', 'two', 'three'];
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['collection'])
            ->getMock();
        $stub->expects($this->once())
            ->method('collection')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_transform_on_unknown_resource_sets_item_resource()
    {
        $data = new \stdClass();
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['item'])
            ->getMock();
        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->transform($data);
    }

    public function test_item_method_sets_smokescreen_item_resource()
    {
        $data = new \stdClass();
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['item'])
            ->getMock();

        $stub->expects($this->once())
            ->method('item')
            ->with($this->equalTo($data), $this->equalTo(null));

        $smokescreen = Smokescreen::make($stub);
        $smokescreen->item($data, null);
    }

    public function test_collection_method_sets_smokescreen_collection_resource()
    {
        $data = [];
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['collection'])
            ->getMock();

        $stub->expects($this->once())
            ->method('collection')
            ->with($this->equalTo($data), $this->equalTo(null));

        $smokescreen = Smokescreen::make($stub);
        $smokescreen->collection($data, null);
    }

    public function test_collection_method_with_paginator_delegates_to_paginate_method()
    {
        $data = new LengthAwarePaginator(['one'], 1, 15);;
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setConstructorArgs([new \Rexlabs\Smokescreen\Smokescreen])
            ->setMethods(['paginate'])
            ->getMock();
        $stub->expects($this->once())
            ->method('paginate')
            ->with($this->equalTo($data), $this->equalTo(null));

        $stub->collection($data, null);
    }


    public function test_paginate_method_sets_smokescreen_collection_resource()
    {
        $data = new LengthAwarePaginator(['one'], 1, 15);
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['collection'])
            ->getMock();
        $stub->expects($this->once())
            ->method('collection');
//            ->with($this->equalTo($data), $this->equalTo(null));

        $smokescreen = Smokescreen::make($stub);
        $smokescreen->paginate($data, null);
    }

    public function test_infer_resource_type_of_declared_item_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class implements ItemResource
        {
        };
        $this->assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_infer_resource_type_of_declared_collection_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class implements CollectionResource
        {
        };
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_infer_resource_type_of_model()
    {
        $smokescreen = Smokescreen::make();

        // Eloquent model
        $data = new class extends Model
        {
        };
        $this->assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));

        // Associative array
        $data = ['name' => 'Bob', 'age' => 21];
        $this->assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));

        // Sequential array
        $data = ['item1', 'item2', 'item3'];
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Anonymous class
        $data = new class
        {
        };
        $this->assertEquals(Smokescreen::TYPE_AMBIGUOUS_RESOURCE, $smokescreen->determineResourceType($data));

        // Laravel collection
        $data = new class extends Collection
        {
        };
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Eloquent collection
        $data = new class extends \Illuminate\Database\Eloquent\Collection
        {
        };
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Paginator
        $data = new class([], 0, 15) extends LengthAwarePaginator
        {
        };
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Query Builder
        $data = $this->createQueryBuilder();
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Eloquent Builder
        $data = new Builder($this->createQueryBuilder());
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // HasMany
        $data = new HasMany(new Builder($this->createQueryBuilder()), $this->createModel(), 'foreign', 'local');
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_set_transformer_without_setting_resource_first_throws_exception()
    {
        $smokescreen = Smokescreen::make();
        $this->expectException(MissingResourceException::class);
        $smokescreen->transformWith($this->createTransformer());
    }

    public function test_set_transformer_with_resource_already_set()
    {
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['setTransformer'])
            ->getMock();
        $stub->expects($this->once())
            ->method('setTransformer');
        $smokescreen = Smokescreen::make($stub);
        $smokescreen->item(new \stdClass());
        $smokescreen->transformWith($this->createTransformer());
    }

    public function test_cant_transform_without_resource()
    {
        $smokescreen = Smokescreen::make();
        $this->expectException(MissingResourceException::class);
        $smokescreen->toArray();
    }

    public function test_set_serializer()
    {
        $data = [
            [
                'id'   => 1,
                'name' => 'Bob',
            ],
            [
                'id'   => 2,
                'name' => 'Walter',
            ],
        ];
        $smokescreen = Smokescreen::make()
            ->transform($data)
            ->serializeWith($this->createSerializer());
        $this->assertEquals(
            [
                'custom_serialize' => $data,
            ], $smokescreen->toArray()
        );
    }

    public function test_set_relation_loader()
    {
        $loader = new RelationLoader();
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['setRelationLoader'])
            ->getMock();
        $stub->expects($this->once())
            ->method('setRelationLoader')
            ->with($this->equalTo($loader));
        $smokescreen = Smokescreen::make($stub);
        $smokescreen->loadRelationsVia($loader);
    }

    public function test_can_convert_to_object()
    {
        $data = [
            [
                'id'   => 1,
                'name' => 'Bob',
            ],
            [
                'id'   => 2,
                'name' => 'Walter',
            ],
        ];
        $smokescreen = Smokescreen::make()
            ->transform($data)
            ->serializeWith($this->createSerializer());
        $result = $smokescreen->toObject();
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertTrue(property_exists($result, 'custom_serialize'));
        $this->assertCount(2, $result->custom_serialize);
        $this->assertInstanceOf(\stdClass::class, $result->custom_serialize[0]);
        $this->assertInstanceOf(\stdClass::class, $result->custom_serialize[1]);
    }

    public function test_includes_are_parsed()
    {
        $data = [
            [
                'id'   => 1,
                'title' => 'Example post 1',
                'body' => 'An example post',
            ],
            [
                'id'   => 2,
                'title' => 'Example post 2',
                'body' => 'Another example post',
            ],
        ];
        $includeStr = 'user,comments';
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['parseIncludes'])
            ->getMock();
        $stub->expects($this->once())
            ->method('parseIncludes')
            ->with($this->equalTo($includeStr));
        $smokescreen = Smokescreen::make($stub)
            ->transform($data, $this->createTransformer())
            ->include($includeStr);

        // Note data, wont actually include our includes since we mocked the parseIncludes method.
        $result = $smokescreen->toArray();
        $this->assertEquals([ 'data' => $data ], $result);
    }

    public function test_transformer_is_resolved_for_model()
    {
        $post = new Post(['id' => 1, 'title' => 'Example post', 'body' => 'Post body']);
        $smokescreen = Smokescreen::make(null, [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
        ]);
        $smokescreen->transform($post);
        $result = $smokescreen->toArray();
        $this->assertEquals([
            'id' => 1,
            'title' => 'Example post',
        ], $result);

    }

    protected function createQueryBuilder(): \Illuminate\Database\Query\Builder
    {
        return new \Illuminate\Database\Query\Builder(
            $this->createMock(ConnectionInterface::class),
            $this->createMock(PostgresGrammar::class),
            $this->createMock(PostgresProcessor::class)
        );
    }

    protected function createModel(): Model
    {
        return new class () extends Model
        {
        };
    }

    protected function createTransformer(): AbstractTransformer
    {
        return new class extends AbstractTransformer
        {
            protected $includes = [
                'user',
                'comments',
            ];

            public function transform($data)
            {
                return $data;
            }

            public function includeUser($data)
            {
                return $this->item(['email' => 'alice@example.com']);
            }

            public function includeComments($data)
            {
                return $this->collection([
                    [
                        'comments' => 'Great post',
                    ],
                    [
                        'comments' => 'I agree',
                    ]
                ]);
            }
        };
    }

    protected function createSerializer(): DefaultSerializer
    {
        return new class extends DefaultSerializer
        {
            public function collection($resourceKey, array $data): array
            {
                return ['custom_serialize' => $data];
            }
        };
    }
}