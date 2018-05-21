<?php

namespace Rexlabs\Laravel\Smokescreen\Tests\Unit;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Processors\PostgresProcessor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException;
use Rexlabs\Laravel\Smokescreen\Relations\RelationLoader;
use Rexlabs\Laravel\Smokescreen\Resources\CollectionResource;
use Rexlabs\Laravel\Smokescreen\Resources\ItemResource;
use Rexlabs\Laravel\Smokescreen\Smokescreen;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\Post;
use Rexlabs\Laravel\Smokescreen\Tests\Stubs\Models\User;
use Rexlabs\Laravel\Smokescreen\Tests\TestCase;
use Rexlabs\Laravel\Smokescreen\Transformers\AbstractTransformer;
use Rexlabs\Smokescreen\Exception\MissingResourceException;
use Rexlabs\Smokescreen\Serializer\DefaultSerializer;
use Rexlabs\Smokescreen\Transformer\TransformerInterface;
use Rexlabs\Smokescreen\Transformer\TransformerResolverInterface;

class SmokescreenTest extends TestCase
{
    public function test_can_get_base_smokescreen_instance()
    {
        $smokescreen = Smokescreen::make();
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Smokescreen::class, $smokescreen->getBaseSmokescreen());
    }

    public function test_transform_on_item_sets_item_resource()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(
            new class() implements ItemResource {
            }
        );
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Item::class, $smokescreen->getResource());
    }

    public function test_transform_on_assoc_array_sets_item_resource()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(
            [
                'name' => 'Bob',
                'age'  => 21,
            ]
        );
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Item::class, $smokescreen->getResource());
    }

    public function test_transform_on_collection_sets_collection_resource()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(
            new class() implements CollectionResource {
            }
        );
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_transform_on_sequential_array_sets_collection_resource()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(['one', 'two', 'three']);
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_transform_on_unknown_resource_sets_item_resource()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->transform(new \stdClass());
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Item::class, $smokescreen->getResource());
    }

    public function test_item_method_sets_item_resource()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->item(new \stdClass());
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Item::class, $smokescreen->getResource());
    }

    public function test_collection_method_sets_collection_resource()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_paginator_sets_collection()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection(new LengthAwarePaginator(['one'], 1, 15));
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_relation_sets_collection()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make();
        $smokescreen->collection(
            Post::first()
                ->comments()
        );
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_builder_sets_collection()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make();
        $smokescreen->collection(Post::where('id', 1));
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_collection_method_with_model_sets_collection()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make();
        $smokescreen->collection(Post::first());
        $this->assertInstanceOf(\Rexlabs\Smokescreen\Resource\Collection::class, $smokescreen->getResource());
    }

    public function test_infer_resource_type_of_declared_item_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class() implements ItemResource {
        };
        $this->assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_infer_resource_type_of_declared_collection_resource()
    {
        $smokescreen = Smokescreen::make();

        $data = new class() implements CollectionResource {
        };
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));
    }

    public function test_infer_resource_type_of_model()
    {
        $smokescreen = Smokescreen::make();

        // Eloquent model
        $data = new class() extends Model {
        };
        $this->assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));

        // Associative array
        $data = ['name' => 'Bob', 'age' => 21];
        $this->assertEquals(Smokescreen::TYPE_ITEM_RESOURCE, $smokescreen->determineResourceType($data));

        // Sequential array
        $data = ['item1', 'item2', 'item3'];
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Anonymous class
        $data = new class() {
        };
        $this->assertEquals(Smokescreen::TYPE_AMBIGUOUS_RESOURCE, $smokescreen->determineResourceType($data));

        // Laravel collection
        $data = new class() extends Collection {
        };
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Eloquent collection
        $data = new class() extends \Illuminate\Database\Eloquent\Collection {
        };
        $this->assertEquals(Smokescreen::TYPE_COLLECTION_RESOURCE, $smokescreen->determineResourceType($data));

        // Paginator
        $data = new class([], 0, 15) extends LengthAwarePaginator {
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
        $smokescreen = Smokescreen::make();
        $smokescreen->item(new \stdClass());
        $this->assertNull(
            $smokescreen->getResource()
                ->getTransformer()
        );
        $smokescreen->transformWith($this->createTransformer());
        $this->assertInstanceOf(
            TransformerInterface::class, $smokescreen->getResource()
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
        $stub->expects($this->once())
            ->method('parseIncludes')
            ->with($this->equalTo($includeStr));
        $smokescreen = Smokescreen::make($stub)
            ->transform($data, $this->createTransformer())
            ->include($includeStr);

        // Note data, wont actually include our includes since we mocked the parseIncludes method.
        $result = $smokescreen->toArray();
        $this->assertEquals(['data' => $data], $result);
    }

    public function test_can_disable_includes()
    {
        $this->createSchemas();
        $this->createModels();

        $smokescreen = Smokescreen::make(null, [
                 'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ])
            ->transform(Post::first())
            ->include('user,comments');

        $this->assertArrayHasKey('user', $smokescreen->toArray());

        $smokescreen->noIncludes();
        $this->assertArrayNotHasKey('user', $smokescreen->toArray());
    }

    public function test_transformer_is_resolved_for_model()
    {
        $this->createSchemas();
        $this->createModels();

        $post = Post::first();
        $smokescreen = Smokescreen::make(
            null, [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
        ]
        );
        $smokescreen->transform($post);
        $smokescreen->toArray();
        $this->assertEquals(
            'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers\\PostTransformer', \get_class(
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
            null, [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
        ]
        );
        $smokescreen->transform(Post::all());
        $smokescreen->toArray();
        $this->assertEquals(
            'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers\\PostTransformer', \get_class(
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
            null, [
            'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
        ]
        );
        $smokescreen->transform(Post::paginate());
        $smokescreen->toArray();
        $this->assertEquals(
            'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers\\PostTransformer', \get_class(
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
            null, [
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
        $this->assertEquals('include', $smokescreen->getIncludeKey());
    }

    public function test_include_key_can_be_configured()
    {
        $smokescreen = Smokescreen::make(null, ['include_key' => 'override']);
        $this->assertEquals('override', $smokescreen->getIncludeKey());
    }

    public function test_include_key_can_be_overridden_in_sub_class()
    {
        $smokescreen = new class(new \Rexlabs\Smokescreen\Smokescreen()) extends Smokescreen {
            protected $autoParseIncludes = 'auto_parse_key';
        };

        $this->assertEquals('auto_parse_key', $smokescreen->getIncludeKey());
    }

    public function test_default_serializer_can_be_configured_with_class_name()
    {
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setMethods(['serializeWith'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->expects($this->once())
            ->method('serializeWith')
            ->with($this->equalTo(new DefaultSerializer()));
        $stub->__construct(
            new \Rexlabs\Smokescreen\Smokescreen(),
            ['default_serializer' => DefaultSerializer::class]
        );
    }

    public function test_default_serializer_can_be_configured_with_object()
    {
        $obj = new class() extends DefaultSerializer {
        };
        $stub = $this->getMockBuilder(Smokescreen::class)
            ->setMethods(['serializeWith'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->expects($this->once())
            ->method('serializeWith')
            ->with($this->equalTo($obj));
        $stub->__construct(
            new \Rexlabs\Smokescreen\Smokescreen(),
            ['default_serializer' => $obj]
        );
    }

    public function test_can_set_request_manually()
    {
        $request = request();
        $request['post_id'] = 1234;

        $smokescreen = Smokescreen::make();
        $smokescreen->setRequest($request);
        $this->assertEquals($request, $smokescreen->request());
    }

    public function test_it_implements_responsable_interface()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $response = $smokescreen->toResponse(request());
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertInstanceOf(\stdClass::class, $response->getData());
        $this->assertEquals([], $response->getData()->data);
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
        $this->assertInstanceOf(JsonResponse::class, $smokescreen->response());
        $this->assertEquals('Some Header', $smokescreen->response()->headers->get('X-Some-Header'));
        $this->assertEquals(
            418, $smokescreen->response()
            ->getStatusCode()
        );
    }

    public function test_response_is_cached()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $response = $smokescreen->response(
            418, [
            'X-Some-Header' => 'Some Header',
        ]
        );
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('Some Header', $response->headers->get('X-Some-Header'));
        $this->assertEquals(418, $response->getStatusCode());

        // Return a new response but cached value is still returned
        $response = $smokescreen->response(
            200, [
            'X-Some-Header' => 'Another Value',
        ]
        );
        $this->assertEquals('Some Header', $response->headers->get('X-Some-Header'));
        $this->assertEquals(418, $response->getStatusCode());
    }

    public function test_fresh_response_is_not_cached()
    {
        $smokescreen = Smokescreen::make();
        $smokescreen->collection([]);
        $response = $smokescreen->response(
            418, [
            'X-Some-Header' => 'Some Header',
        ]
        );
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals('Some Header', $response->headers->get('X-Some-Header'));
        $this->assertEquals(418, $response->getStatusCode());

        // Return a new response but cached value is still returned
        $response = $smokescreen->freshResponse(
            200, [
            'X-Some-Header' => 'Another Value',
        ]
        );
        $this->assertEquals('Another Value', $response->headers->get('X-Some-Header'));
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_can_override_the_transformer_resolver()
    {
        $this->createSchemas();
        $this->createModels();

        // Mock a resolver, and test that the resolve() method is called.
        $stub = $this->getMockBuilder(TransformerResolverInterface::class)
            ->getMock();
        $stub->expects($this->once())
            ->method('resolve');

        $smokescreen = Smokescreen::make(
            null, [
                'transformer_namespace' => 'Rexlabs\\Laravel\\Smokescreen\\Tests\\Stubs\\Transformers',
            ]
        );
        $smokescreen
            ->resolveTransformerVia($stub)
            ->transform(User::first())
            ->toArray();
    }

    protected function createQueryBuilder(): \Illuminate\Database\Query\Builder
    {
        return new \Illuminate\Database\Query\Builder(
            $this->createMock(ConnectionInterface::class), $this->createMock(PostgresGrammar::class),
            $this->createMock(PostgresProcessor::class)
        );
    }

    protected function createModel(): Model
    {
        return new class() extends Model {
        };
    }

    protected function createTransformer(): AbstractTransformer
    {
        return new class() extends AbstractTransformer {
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
        return new class() extends DefaultSerializer {
            public function collection($resourceKey, array $data): array
            {
                return ['custom_serialize' => $data];
            }
        };
    }

    protected function createSchemas()
    {
        Schema::create(
            'users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('email')
                ->unique();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            }
        );
        Schema::create(
            'posts', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('title');
                $table->text('body');
                $table->timestamps();
            }
        );
        Schema::create(
            'comments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('post_id');
                $table->unsignedInteger('user_id');
                $table->string('title');
                $table->text('comments');
                $table->timestamps();
            }
        );
    }

    protected function createModels()
    {
        /** @var User $user */
        $user = User::create(
            [
                'name'     => 'Some User',
                'email'    => 'some.user@example.com',
                'password' => Hash::make('somepassword'),
            ]
        );
        /** @var Post $post */
        $post = Post::create(
            [
                'user_id' => $user->id,
                'title'   => 'Example post',
                'body'    => 'Post body',
            ]
        );
        $post->comments()
            ->create(
                [
                    'user_id'  => $user->id,
                    'title'    => 'First comment',
                    'comments' => 'FP',
                ]
            );
        $post->comments()
            ->create(
                [
                    'user_id'  => $user->id,
                    'title'    => 'Another comment',
                    'comments' => 'That is all',
                ]
            );
    }
}
