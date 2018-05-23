<?php

namespace Rexlabs\Laravel\Smokescreen;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Rexlabs\Laravel\Smokescreen\Pagination\Paginator as PaginatorBridge;
use Rexlabs\Laravel\Smokescreen\Relations\RelationLoader;
use Rexlabs\Laravel\Smokescreen\Resources\CollectionResource;
use Rexlabs\Laravel\Smokescreen\Resources\ItemResource;
use Rexlabs\Laravel\Smokescreen\Transformers\TransformerResolver;
use Rexlabs\Smokescreen\Exception\MissingResourceException;
use Rexlabs\Smokescreen\Helpers\JsonHelper;
use Rexlabs\Smokescreen\Relations\RelationLoaderInterface;
use Rexlabs\Smokescreen\Resource\Item;
use Rexlabs\Smokescreen\Resource\ResourceInterface;
use Rexlabs\Smokescreen\Serializer\SerializerInterface;
use Rexlabs\Smokescreen\Transformer\TransformerInterface;
use Rexlabs\Smokescreen\Transformer\TransformerResolverInterface;

/**
 * Smokescreen for Laravel.
 * Tightly integrates the rexlabs/smokescreen resource transformation library with the Laravel framework.
 *
 * @author    Jodie Dunlop <jodie.dunlop@rexsoftware.com.au>
 * @copyright Rex Software 2018
 */
class Smokescreen implements \JsonSerializable, Jsonable, Arrayable, Responsable
{
    const TYPE_ITEM_RESOURCE = 'item';
    const TYPE_COLLECTION_RESOURCE = 'collection';
    const TYPE_AMBIGUOUS_RESOURCE = 'ambiguous';

    /** @var \Rexlabs\Smokescreen\Smokescreen */
    protected $smokescreen;

    /** @var string|null */
    protected $includes;

    /** @var string|bool Whether includes should be parsed from a request key */
    protected $autoParseIncludes = true;

    /** @var SerializerInterface|null */
    protected $serializer;

    /** @var Request|null */
    protected $request;

    /** @var Response|null */
    protected $response;

    /** @var ResourceInterface|null */
    protected $resource;

    /** @var array */
    protected $config;

    /** @var array */
    protected $injections;

    /**
     * Smokescreen constructor.
     *
     * @param \Rexlabs\Smokescreen\Smokescreen $smokescreen
     * @param array                            $config
     */
    public function __construct(\Rexlabs\Smokescreen\Smokescreen $smokescreen, array $config = [])
    {
        $this->smokescreen = $smokescreen;
        $this->setConfig($config);
    }

    /**
     * Creates a new Smokescreen object.
     *
     * @param \Rexlabs\Smokescreen\Smokescreen|null $smokescreen
     * @param array                                 $config
     *
     * @return static
     */
    public static function make(\Rexlabs\Smokescreen\Smokescreen $smokescreen = null, array $config = [])
    {
        return new static($smokescreen ?? new \Rexlabs\Smokescreen\Smokescreen(), $config);
    }

    /**
     * Set the resource (item or collection) data to be transformed.
     * You should pass in an instance of a Model.
     *
     * @param mixed|Model|array                  $data
     * @param callable|TransformerInterface|null $transformer
     * @param string|null                        $resourceKey
     *
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function transform($data, $transformer = null, $resourceKey = null)
    {
        switch ($this->determineResourceType($data)) {
            case self::TYPE_ITEM_RESOURCE:
                $this->item($data, $transformer, $resourceKey);
                break;
            case self::TYPE_COLLECTION_RESOURCE:
                $this->collection($data, $transformer, $resourceKey);
                break;
            default:
                $this->item($data, $transformer, $resourceKey);
                break;
        }

        return $this;
    }

    /**
     * @param mixed $data
     *
     * @return string
     */
    public function determineResourceType($data): string
    {
        if ($data instanceof ItemResource) {
            // Explicitly declared itself as an Item
            return self::TYPE_ITEM_RESOURCE;
        }

        if ($data instanceof CollectionResource) {
            // Explicitly declared itself as a Collection
            return self::TYPE_COLLECTION_RESOURCE;
        }

        if ($data instanceof Model) {
            // Eloquent model treated as an item by default
            return self::TYPE_ITEM_RESOURCE;
        }

        if ($data instanceof Collection) {
            // Is an instance or extended class of Laravel Support\Collection
            return self::TYPE_COLLECTION_RESOURCE;
        }

        if ($data instanceof \Illuminate\Database\Eloquent\Builder || $data instanceof \Illuminate\Database\Query\Builder) {
            // Treat query builders as a collection
            return self::TYPE_COLLECTION_RESOURCE;
        }

        if ($data instanceof LengthAwarePaginator) {
            // Is an instance of Pagination
            return self::TYPE_COLLECTION_RESOURCE;
        }

        if ($data instanceof HasMany || $data instanceof HasManyThrough || $data instanceof BelongsToMany) {
            // Many relationships are treated as a collection
            return self::TYPE_COLLECTION_RESOURCE;
        }

        if ($data instanceof Arrayable) {
            // Get array data for Arrayable so that we can determine resource type
            $data = $data->toArray();
        }

        if (\is_array($data)) {
            // Handle plain arrays
            if (Arr::isAssoc($data)) {
                // Associative arrays are treated as items
                return self::TYPE_ITEM_RESOURCE;
            }

            // All other arrays are considered collections
            return self::TYPE_COLLECTION_RESOURCE;
        }

        // Everything else is ambiguous resource type
        return self::TYPE_AMBIGUOUS_RESOURCE;
    }

    /**
     * Set an item resource to be transformed.
     *
     * @param mixed                              $data
     * @param callable|TransformerInterface|null $transformer
     * @param string|null                        $resourceKey
     *
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function item($data, $transformer = null, $resourceKey = null)
    {
        $this->setResource(new Item($data, $transformer, $resourceKey));

        return $this;
    }

    /**
     * Set a collection resource to be transformed.
     *
     * @param mixed                              $data
     * @param callable|TransformerInterface|null $transformer
     * @param string|null                        $resourceKey
     *
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function collection($data, $transformer = null, $resourceKey = null)
    {
        $paginator = null;

        if ($data instanceof LengthAwarePaginator) {
            $paginator = $data;
            $data = $data->getCollection();
        } elseif ($data instanceof Relation) {
            $data = $data->get();
        } elseif ($data instanceof Builder) {
            $data = $data->get();
        } elseif ($data instanceof Model) {
            $data = new Collection([$data]);
        }

        // Create a new collection resource
        $resource = new \Rexlabs\Smokescreen\Resource\Collection($data, $transformer, $resourceKey);
        if ($paginator !== null) {
            // Assign any paginator to the resource
            $resource->setPaginator(new PaginatorBridge($paginator));
        }
        $this->setResource($resource);

        return $this;
    }

    /**
     * Set the transformer used to transform the resource(s).
     *
     * @param TransformerInterface|callable|null $transformer
     *
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function transformWith($transformer)
    {
        if ($this->resource === null) {
            throw new MissingResourceException('Cannot set transformer before setting resource');
        }
        $this->resource->setTransformer($transformer);

        return $this;
    }

    /**
     * Set the default serializer to be used for resources which do not have an explictly set serializer.
     *
     * @param SerializerInterface|null $serializer
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function serializeWith($serializer)
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Set the relationship loader.
     * The relationship loader takes the relationships defined on a transformer, and eager-loads them.
     *
     * @param RelationLoaderInterface $relationLoader
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function loadRelationsVia(RelationLoaderInterface $relationLoader)
    {
        $this->smokescreen->setRelationLoader($relationLoader);

        return $this;
    }

    /**
     * Sets the resolver to be used for locating transformers for resources.
     *
     * @param TransformerResolverInterface $transformerResolver
     *
     * @return $this
     */
    public function resolveTransformerVia(TransformerResolverInterface $transformerResolver)
    {
        $this->smokescreen->setTransformerResolver($transformerResolver);

        return $this;
    }

    /**
     * Returns an object representation of the transformed/serialized data.
     *
     * @throws \Rexlabs\Smokescreen\Exception\JsonEncodeException
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     *
     * @return \stdClass
     */
    public function toObject(): \stdClass
    {
        return json_decode($this->toJson(), false);
    }

    /**
     * Outputs a JSON string of the resulting transformed and serialized data.
     * Implements Laravel's Jsonable interface.
     *
     * @param int $options
     *
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\JsonEncodeException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     *
     * @return string
     */
    public function toJson($options = 0): string
    {
        return JsonHelper::encode($this->jsonSerialize(), $options);
    }

    /**
     * Output the transformed and serialized data as an array.
     * Implements PHP's JsonSerializable interface.
     *
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     * @throws \Rexlabs\Smokescreen\Exception\IncludeException
     *
     * @return array
     *
     * @see Smokescreen::toArray()
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Inject some data into the payload under given key (supports dot-notation).
     * This method can be called multiple times.
     *
     * @param string $key
     * @param mixed  $data
     *
     * @return $this
     */
    public function inject($key, $data)
    {
        $this->injections[$key] = $data;

        return $this;
    }

    /**
     * Output the transformed and serialized data as an array.
     * This kicks off the transformation via the base Smokescreen object.
     *
     * @throws \Rexlabs\Smokescreen\Exception\UnhandledResourceType
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     * @throws \Rexlabs\Smokescreen\Exception\IncludeException
     *
     * @return array
     */
    public function toArray(): array
    {
        // We must have a resource provided to transform.
        if ($this->resource === null) {
            throw new MissingResourceException('Resource is not defined');
        }

        // Assign the resource in the base instance.
        $this->smokescreen->setResource($this->resource);

        // Serializer may be overridden via config.
        // We may be setting the serializer to null, in which case a default
        // will be provided.
        $serializer = $this->serializer ?? null;
        $this->smokescreen->setSerializer($serializer);

        // Assign any includes.
        if ($this->includes) {
            // Includes have been set explicitly.
            $this->smokescreen->parseIncludes($this->includes);
        } elseif ($this->autoParseIncludes) {
            // If autoParseIncludes is not false, then try to parse from the
            // request object.
            $this->smokescreen->parseIncludes((string) $this->request()->input($this->getIncludeKey()));
        } else {
            // Empty includes
            $this->smokescreen->parseIncludes('');
        }

        // Provide a custom transformer resolver which can interrogate the
        // underlying model and attempt to resolve a transformer class.
        if ($this->smokescreen->getTransformerResolver() === null) {
            $this->smokescreen->setTransformerResolver(
                new TransformerResolver(
                    $this->config['transformer_namespace'] ?? 'App\\Transformers',
                    $this->config['transformer_name'] ?? '{ModelName}Transformer'
                )
            );
        }

        // We will provide the Laravel relationship loader if none has already
        // been explicitly defined.
        if (!$this->smokescreen->hasRelationLoader()) {
            $this->smokescreen->setRelationLoader(new RelationLoader());
        }

        // Kick off the transformation via the Smokescreen base library.
        $data = $this->smokescreen->toArray();
        if (!empty($this->injections)) {
            foreach ($this->injections as $key => $inject) {
                Arr::set($data, $key, $inject);
            }
        }

        return $data;
    }

    /**
     * Get a Laravel request object.  If not set explicitly via setRequest(...) then
     * it will be automatically resolved out of the container. You're welcome.
     *
     * @return \Illuminate\Http\Request
     */
    public function request(): Request
    {
        if ($this->request === null) {
            // Resolve request out of the container.
            $this->request = app('request');
        }

        return $this->request;
    }

    /**
     * Determine which key is used for the includes when passing from the Request
     * If the autoParseIncludes property is set to a string value this will be used
     * otherwise, the 'include_key' from the configuration.
     * Defaults to 'include'.
     *
     * @return string
     */
    public function getIncludeKey(): string
    {
        if (\is_string($this->autoParseIncludes)) {
            // When set to a string value, indicates the include key
            return $this->autoParseIncludes;
        }

        return $this->config['include_key'] ?? 'include';
    }

    /**
     * Generates a Response object.
     * Implements Laravel's Responsable contract, so that you can return smokescreen object from a controller.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Rexlabs\Smokescreen\Exception\UnhandledResourceType
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function toResponse($request)
    {
        return $this->response();
    }

    /**
     * Return a JsonResponse object containing the resolved/compiled JSON data.
     * Note, since the generated Response is cached, consecutive calls to response() will not change the
     * response based on the given parameters. You can use withResponse($callback) to easily modify the response,
     * or via $this->response()->setStatusCode() etc.
     *
     * @param int   $statusCode
     * @param array $headers
     * @param int   $options
     *
     * @throws \Rexlabs\Smokescreen\Exception\UnhandledResourceType
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @see Smokescreen::toArray()
     */
    public function response(int $statusCode = 200, array $headers = [], int $options = 0): JsonResponse
    {
        // Response will only be generated once. use clearResponse() to clear.
        if ($this->response === null) {
            $this->response = new JsonResponse($this->toArray(), $statusCode, $headers, $options);
        }

        return $this->response;
    }

    /**
     * Returns a fresh (uncached) response.
     * See the response() method.
     *
     * @param int   $statusCode
     * @param array $headers
     * @param int   $options
     *
     * @throws \Rexlabs\Smokescreen\Exception\UnhandledResourceType
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @see Smokescreen::toArray()
     * @see Smokescreen::response()
     */
    public function freshResponse(int $statusCode = 200, array $headers = [], int $options = 0): JsonResponse
    {
        $this->clearResponse();

        return $this->response($statusCode, $headers, $options);
    }

    /**
     * Clear the cached response object.
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function clearResponse()
    {
        $this->response = null;

        return $this;
    }

    /**
     * Apply a callback to the response.  The response will be generated if it has not already been.
     *
     * @param callable $apply
     *
     * @throws \Rexlabs\Smokescreen\Exception\UnhandledResourceType
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function withResponse(callable $apply)
    {
        $apply($this->response());

        return $this;
    }

    /**
     * Set the include string.
     *
     * @param string|null $includes
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function include($includes)
    {
        $this->includes = $includes === null ? $includes : (string) $includes;

        return $this;
    }

    /**
     * Disable all includes.
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function noIncludes()
    {
        $this->includes = null;
        $this->autoParseIncludes = false;

        return $this;
    }

    /**
     * Set the Laravel request object which will be used to resolve parameters.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

        return $this;
    }

    /**
     * @return null|ResourceInterface
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param $resource null|ResourceInterface
     *
     * @return Smokescreen
     */
    public function setResource($resource)
    {
        $this->resource = $resource;

        // Clear any cached response when the resource changes
        $this->clearResponse();

        return $this;
    }

    /**
     * Get the underlying Smokescreen instance that we are wrapping in our laravel-friendly layer.
     *
     * @return \Rexlabs\Smokescreen\Smokescreen
     */
    public function getBaseSmokescreen(): \Rexlabs\Smokescreen\Smokescreen
    {
        return $this->smokescreen;
    }

    /**
     * @param array $config
     */
    protected function setConfig(array $config)
    {
        if (!empty($config['default_serializer'])) {
            $serializer = $config['default_serializer'];
            if (\is_string($serializer)) {
                // Given serializer is expected to be a class path
                // Instantiate via the container
                $serializer = app()->make($serializer);
            }
            $this->serializeWith($serializer);
            unset($config['default_serializer']);
        }

        $this->config = $config;
    }
}
