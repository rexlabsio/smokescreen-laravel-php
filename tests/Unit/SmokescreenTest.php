<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException;
use Rexlabs\Laravel\Smokescreen\Relations\RelationLoader;
use Rexlabs\Laravel\Smokescreen\Resources\CollectionResource;
use Rexlabs\Laravel\Smokescreen\Resources\ItemResource;
use Rexlabs\Laravel\Smokescreen\Smokescreen;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\Post;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\User;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Transformers\PostTransformer;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;
use Rexlabs\Laravel\Smokescreen\Tests\UsesModelStubs;
use Rexlabs\Laravel\Smokescreen\Transformers\AbstractTransformer;
use Rexlabs\Laravel\Smokescreen\Transformers\TransformerResolver;
use Rexlabs\Smokescreen\Exception\MissingResourceException;
use Rexlabs\Smokescreen\Resource\Item;
use Rexlabs\Smokescreen\Serializer\DefaultSerializer;
use Rexlabs\Smokescreen\Transformer\TransformerInterface;
use Rexlabs\Smokescreen\Transformer\TransformerResolverInterface;

/**
 * Class SmokescreenTest
 * @package Rexlabs\Laravel\Smokescreen\Tests\Unit
 */
class SmokescreenTest extends TestCase
{
    use UsesModelStubs;

    public function test_can_get_base_smokescreen_instance(): void
    {
        $smokescreen = Smokescreen::make();
        self::assertInstanceOf(\Rexlabs\Smokescreen\Smokescreen::class, $smokescreen->getBaseSmokescreen());
    }

    public function test_transform_on_item_sets_item_resource(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(
            new class () implements ItemResource {
            }
        );
        self::assertInstanceOf(Item::class, $smokescreen->getResource());
    }

    public function test_transform_on_assoc_array_sets_item_resource(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(
            [
                'name' => 'Bob',
                'age'  => 21,
            ]
        );
        self::assertInstanceOf(Item::class, $smokescreen->getResource());
    }

    public function test_transform_on_collection_sets_collection_resource(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(
            new class () implements CollectionResource {
            }
        );
        self::assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_transform_on_sequential_array_sets_collection_resource(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(['one', 'two', 'three']);
        self::assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_transform_on_unknown_resource_sets_item_resource(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(new \stdClass());
        self::assertInstanceOf(Item::class, $smokescreen->getResource());
    }

    public function test_item_method_sets_item_resource(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->item(new \stdClass());
        self::assertInstanceOf(Item::class, $smokescreen->getResource());
    }

    public function test_collection_method_sets_collection_resource(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        self::assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_paginator_sets_collection(): void
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection(new LengthAwarePaginator(['one'], 1, 15));
        self::assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_relation_sets_collection(): void
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make();
        $smokescreen->collection(
            Post::first()
                ->comments()
        );
        self::assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_builder_sets_collection(): void
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make();
        $smokescreen->collection(Post::where('id', 1));
        self::assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_model_sets_collection(): void
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make();
        $smokescreen->collection(Post::first());
        self::assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_infer_resource_type_of_declared_item_resource(): void
    {
        $smokescreen = Smokescreen::make();

        $data = new class () implements ItemResource {
        };
        self::assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_infer_resource_type_of_declared_collection_resource(): void
    {
        $smokescreen = Smokescreen::make();

        $data = new class () implements CollectionResource {
        };
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_infer_resource_type_of_model(): void
    {
        $smokescreen = Smokescreen::make();

        // Eloquent model
        $data = new class () extends Model {
        };
        self::assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));

        // Associative array
        $data = ['name' => 'Bob', 'age' => 21];
        self::assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));

        // Sequential array
        $data = ['item1', 'item2', 'item3'];
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Anonymous class
        $data = new class () {
        };
        self::assertEquals(Smokescreen::TYPE_AMBIGUOUS_RESOURCE, $smokescreen->determineResourceType($data));

        // Laravel collection
        $data = new class () extends Collection {
        };
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Eloquent collection
        $data = new class () extends \Illuminate\Database\Eloquent\Collection {
        };
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Paginator
        $data = new class ([], 0, 15) extends LengthAwarePaginator {
        };
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Query Builder
        $data = $this->createQueryBuilder();
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Eloquent Builder
        $data = new Builder($this->createQueryBuilder());
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // HasMany
        $data = new HasMany(new Builder($this->createQueryBuilder()), $this->createModel(), 'foreign', 'local');
        self::assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_item_method_can_load_transformer_from_container()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->item(new \stdClass(), PostTransformer::class);
        $transformer = $smokescreen->getResource()->getTransformer();
        self::assertInstanceOf(PostTransformer::class, $transformer);
    }

    public function test_collection_method_can_load_transformer_from_container()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([], PostTransformer::class);
        $transformer = $smokescreen->getResource()->getTransformer();
        self::assertInstanceOf(PostTransformer::class, $transformer);
    }

    public function test_set_transformer_without_setting_resource_first_throws_exception()
    {
        $smokescreen = Smokescreen::make();
        $this->expectException(MissingResourceException::class);
        $smokescreen->transformWith($this->createTransformer());
    }

    public function test_set_transformer_with_resource_already_set()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->item(new \stdClass());
        self::assertNull(
            $smokescreen->getResource()
                ->getTransformer()
        );
        $smokescreen->transformWith($this->createTransformer());
        self::assertInstanceOf(
            TransformerInterface::class,
            $smokescreen->getResource()
                ->getTransformer()
        );
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
        self::assertEquals(
            [
                'custom_serialize' => $data,
            ],
            $smokescreen->toArray()
        );
    }

    public function test_set_relation_loader()
    {
        $loader = new RelationLoader();
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['setRelationLoader'])
            ->getMock();
        $stub->expects(self::once())
            ->method('setRelationLoader')
            ->with(self::equalTo($loader));
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
        self::assertInstanceOf(\stdClass::class, $result);
        self::assertTrue(property_exists($result, 'custom_serialize'));
        self::assertCount(2, $result->custom_serialize);
        self::assertInstanceOf(\stdClass::class, $result->custom_serialize[0]);
        self::assertInstanceOf(\stdClass::class, $result->custom_serialize[1]);
    }

    public function test_includes_are_parsed()
    {
        $data = [
            [
                'id'    => 1,
                'title' => 'Example post 1',
                'body'  => 'An example post',
            ],
            [
                'id'    => 2,
                'title' => 'Example post 2',
                'body'  => 'Another example post',
            ],
        ];
        $includeStr = 'user,comments';
        $stub = $this->getMockBuilder(\Rexlabs\Smokescreen\Smokescreen::class)
            ->setMethods(['parseIncludes'])
            ->getMock();
        $stub->expects(self::once())
            ->method('parseIncludes')
            ->with(self::equalTo($includeStr));
        $smokescreen = Smokescreen::make($stub)
            ->transform($data, $this->createTransformer())
            ->include($includeStr);

        // Note data, wont actually include our includes since we mocked the parseIncludes method.
        $result = $smokescreen->toArray();
        self::assertEquals(['data' => $data], $result);
    }

    public function test_can_disable_includes()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make(
            null,
            [
                 'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ]
        )
            ->transform(Post::first())
            ->include('user,comments');

        self::assertArrayHasKey('user', $smokescreen->toArray());

        $smokescreen->noIncludes();
        self::assertArrayNotHasKey('user', $smokescreen->toArray());
    }

    public function test_transformer_is_resolved_for_model()
    {
        $this->createSchemas();
        $this->createModels();

        $post = Post::first();
        $smokescreen = Smokescreen::make(
            null,
            [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ]
        );
        $smokescreen->transform($post);
        $smokescreen->toArray();
        self::assertEquals(
            'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers\\PostTransformer',
            \get_class(
                $smokescreen->getResource()
                    ->getTransformer()
            )
        );
    }

    public function test_transformer_is_resolved_for_collection()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make(
            null,
            [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ]
        );
        $smokescreen->transform(Post::all());
        $smokescreen->toArray();
        self::assertEquals(
            'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers\\PostTransformer',
            \get_class(
                $smokescreen->getResource()
                    ->getTransformer()
            )
        );
    }

    public function test_transformer_is_resolved_for_paginator()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make(
            null,
            [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ]
        );
        $smokescreen->transform(Post::paginate());
        $smokescreen->toArray();
        self::assertEquals(
            'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers\\PostTransformer',
            \get_class(
                $smokescreen->getResource()
                    ->getTransformer()
            )
        );
    }

    public function test_unresolved_transformer_throws_exception()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make(
            null,
            [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ]
        );
        $smokescreen->transform(User::first());
        $this->expectException(UnresolvedTransformerException::class);
        $smokescreen->toArray();
    }

    public function test_include_key_defaults_to_include()
    {
        $smokescreen = Smokescreen::make();
        self::assertEquals('include', $smokescreen->getIncludeKey());
    }

    public function test_include_key_can_be_configured()
    {
        $smokescreen = Smokescreen::make(null, ['include_key' => 'override']);
        self::assertEquals('override', $smokescreen->getIncludeKey());
    }

    public function test_include_key_can_be_overridden_in_sub_class()
    {
        $smokescreen = new class (new \Rexlabs\Smokescreen\Smokescreen()) extends Smokescreen {
            protected $autoParseIncludes = 'auto_parse_key';
        };

        self::assertEquals('auto_parse_key', $smokescreen->getIncludeKey());
    }

    public function test_default_serializer_can_be_configured_with_class_name()
    {
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setMethods(['serializeWith'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->expects(self::once())
            ->method('serializeWith')
            ->with(self::equalTo(new DefaultSerializer()));
        $stub->__construct(
            new \Rexlabs\Smokescreen\Smokescreen(),
            ['default_serializer' => DefaultSerializer::class]
        );
    }

    public function test_default_serializer_can_be_configured_with_object()
    {
        $obj = new class () extends DefaultSerializer {
        };
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setMethods(['serializeWith'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->expects(self::once())
            ->method('serializeWith')
            ->with(self::equalTo($obj));
        $stub->__construct(
            new \Rexlabs\Smokescreen\Smokescreen(),
            ['default_serializer' => $obj]
        );
    }

    public function test_default_transformer_resolver_can_be_configured_with_class_name()
    {
        $this->app->bind(
            TransformerResolver::class,
            function () {
                return new TransformerResolver('', '');
            }
        );
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setMethods(['resolveTransformerVia'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->expects(self::once())
            ->method('resolveTransformerVia')
            ->with(self::equalTo(new TransformerResolver('', '')));
        $stub->__construct(
            new \Rexlabs\Smokescreen\Smokescreen(),
            ['default_transformer_resolver' => TransformerResolver::class]
        );
    }

    public function test_default_transformer_resolver_can_be_configured_with_object()
    {
        $obj = new class ('', '') extends TransformerResolver {
        };
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setMethods(['resolveTransformerVia'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->expects(self::once())
            ->method('resolveTransformerVia')
            ->with(self::equalTo($obj));
        $stub->__construct(
            new \Rexlabs\Smokescreen\Smokescreen(),
            ['default_transformer_resolver' => $obj]
        );
    }

    public function test_can_set_request_manually()
    {
        $request = request();
        $request['post_id'] = 1234;

        $smokescreen = Smokescreen::make();
        $smokescreen->setRequest($request);
        self::assertEquals($request, $smokescreen->request());
    }

    public function test_it_implements_responsable_interface()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $response = $smokescreen->toResponse(request());
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertInstanceOf(\stdClass::class, $response->getData());
        self::assertEquals([], $response->getData()->data);
    }

    public function test_can_modify_response()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $smokescreen->withResponse(
            function (JsonResponse $response) {
                $response->header('X-Some-Header', 'Some Header')
                    ->setStatusCode(418, "I'm a teapot");
            }
        );
        self::assertInstanceOf(JsonResponse::class, $smokescreen->response());
        self::assertEquals('Some Header', $smokescreen->response()->headers->get('X-Some-Header'));
        self::assertEquals(
            418,
            $smokescreen->response()
                ->getStatusCode()
        );
    }

    public function test_response_is_cached()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $response = $smokescreen->response(
            418,
            [
            'X-Some-Header' => 'Some Header',
            ]
        );
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals('Some Header', $response->headers->get('X-Some-Header'));
        self::assertEquals(418, $response->getStatusCode());

        // Return a new response but cached value is still returned
        $response = $smokescreen->response(
            200,
            [
            'X-Some-Header' => 'Another Value',
            ]
        );
        self::assertEquals('Some Header', $response->headers->get('X-Some-Header'));
        self::assertEquals(418, $response->getStatusCode());
    }

    public function test_fresh_response_is_not_cached()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $response = $smokescreen->response(
            418,
            [
            'X-Some-Header' => 'Some Header',
            ]
        );
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals('Some Header', $response->headers->get('X-Some-Header'));
        self::assertEquals(418, $response->getStatusCode());

        // Return a new response but cached value is still returned
        $response = $smokescreen->freshResponse(
            200,
            [
            'X-Some-Header' => 'Another Value',
            ]
        );
        self::assertEquals('Another Value', $response->headers->get('X-Some-Header'));
        self::assertEquals(200, $response->getStatusCode());
    }

    public function test_can_override_the_transformer_resolver()
    {
        $this->createSchemas();
        $this->createModels();

        // Mock a resolver, and test that the resolve() method is called.
        $stub = $this->getMockBuilder(TransformerResolverInterface::class)
            ->getMock();
        $stub->expects(self::once())
            ->method('resolve');

        $smokescreen = Smokescreen::make(
            null,
            [
                'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ]
        );
        $smokescreen
            ->resolveTransformerVia($stub)
            ->transform(User::first())
            ->toArray();
    }

    public function test_can_handle_null_resource()
    {
        $transformer = new class () extends AbstractTransformer {
            protected $includes = [
                'user' => 'default',
                'comments' => 'default',
            ];

            public function transform($data)
            {
                return $data;
            }

            public function includeUser($data)
            {
                return $this->item(null);
            }

            public function includeComments($data)
            {
                return $this->collection(null);
            }
        };

        $defaultSerializer = new DefaultSerializer();
        $smokescreen = Smokescreen::make();
        $smokescreen->item(
            [
            'id' => '1234',
            'email' => 'bob@example.com',
            ],
            $transformer
        );
        $output = $smokescreen->toArray();
        self::assertEquals($defaultSerializer->nullCollection(), $output['comments']);
        self::assertEquals($defaultSerializer->nullItem(), $output['user']);
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
        return new class () extends Model {
        };
    }

    protected function createTransformer(): AbstractTransformer
    {
        return new class () extends AbstractTransformer {
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
                return $this->collection(
                    [
                        [
                            'comments' => 'Great post',
                        ],
                        [
                            'comments' => 'I agree',
                        ],
                    ]
                );
            }
        };
    }

    protected function createSerializer(): DefaultSerializer
    {
        return new class () extends DefaultSerializer {
            public function collection($resourceKey, array $data): array
            {
                return ['custom_serialize' => $data];
            }
        };
    }

    public function test_can_inject_data_into_payload()
    {
        $data = [
            [
                'id'    => 1,
                'title' => 'Example post 1',
                'body'  => 'An example post',
            ],
            [
                'id'    => 2,
                'title' => 'Example post 2',
                'body'  => 'Another example post',
            ],
        ];

        $smokescreen = Smokescreen::make()
            ->transform($data, $this->createTransformer())
            // Add a a pagination array nested under meta
            ->inject(
                'meta.pagination',
                [
                'next' => 'test1',
                'prev' => 'test2',
                ]
            )
            // Insert a property into the first element in the collection
            ->inject('data.0.new_property', 'val');

        self::assertEquals(
            [
                'data' => [
                    [
                        'id'           => 1,
                        'title'        => 'Example post 1',
                        'body'         => 'An example post',
                        'new_property' => 'val',
                    ],
                    [
                        'id'    => 2,
                        'title' => 'Example post 2',
                        'body'  => 'Another example post',
                    ],
                ],
                'meta' => [
                    'pagination' => [
                        'next' => 'test1',
                        'prev' => 'test2',
                    ],
                ],
            ],
            $smokescreen->toArray()
        );
    }
}
