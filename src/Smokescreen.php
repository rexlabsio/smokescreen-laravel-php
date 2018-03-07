<?php

namespace Rexlabs\Laravel\Smokescreen;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException;
use Rexlabs\Laravel\Smokescreen\Pagination\Paginator as PaginatorBridge;
use Rexlabs\Laravel\Smokescreen\Relations\RelationLoader;
use Rexlabs\Laravel\Smokescreen\Resources\CollectionResource;
use Rexlabs\Laravel\Smokescreen\Resources\ItemResource;
use Rexlabs\Laravel\Smokescreen\Transformers\EmptyTransformer;
use Rexlabs\Smokescreen\Relations\RelationLoaderInterface;
use Rexlabs\Smokescreen\Resource\ResourceInterface;
use Rexlabs\Smokescreen\Serializer\SerializerInterface;
use Rexlabs\Smokescreen\Transformer\TransformerInterface;

/**
 * Laravel Smokescreen.
 * Tightly integrates the rexlabs/smokescreen resource transformation library with the Laravel framework.
 * @package   Rexlabs\Laravel\Smokescreen
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

    /** @var mixed|null */
    protected $serializer;

    /** @var Request|null */
    protected $request;

    /** @var Response|null */
    protected $response;

    public function __construct(\Rexlabs\Smokescreen\Smokescreen $smokescreen)
    {
        $this->smokescreen = $smokescreen;
    }

    /**
     * Creates a new Smokescreen object
     *
     * @param \Rexlabs\Smokescreen\Smokescreen|null $smokescreen
     *
     * @return static
     */
    public static function make(\Rexlabs\Smokescreen\Smokescreen $smokescreen = null)
    {
        return new static($smokescreen ?? new \Rexlabs\Smokescreen\Smokescreen());
    }

    /**
     * Set the resource (item or collection) data to be transformed.
     * You should pass in an instance of a Model.
     * @param mixed|Model|array                  $data
     * @param callable|TransformerInterface|null $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function transform($data, $transformer = null)
    {
        $inferredType = $this->determineResourceType($data);

        switch ($inferredType) {
            case self::TYPE_ITEM_RESOURCE:
                $this->item($data, $transformer);
                break;
            case self::TYPE_COLLECTION_RESOURCE:
                $this->collection($data, $transformer);
                break;
            default:
                $this->item($data, $transformer);
                break;
        }

        return $this;
    }

    /**
     *
     * @param mixed $data
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

        if ($data instanceof HasOneOrMany) {
            // Can't assume a type from this type of relationship
            return self::TYPE_AMBIGUOUS_RESOURCE;
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
     * @param mixed                              $data
     * @param callable|TransformerInterface|null $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function item($data, $transformer = null)
    {
        $this->smokescreen->item($data, $transformer);

        return $this;
    }

    /**
     * Set a collection resource to be transformed.
     * @param mixed                              $data
     * @param callable|TransformerInterface|null $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function collection($data, $transformer = null)
    {
        // Since we're nice, we'll allow a Laravel paginator to be passed to the collection method
        // and hand off to the more specific paginate method.
        if ($data instanceof LengthAwarePaginator) {
            return $this->paginate($data, $transformer);
        }

        $this->smokescreen->collection($data, $transformer);

        return $this;
    }

    /**
     * Set a paginator (aka collection) resource to be transformed.
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @param callable|TransformerInterface|null          $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function paginate(LengthAwarePaginator $paginator, $transformer = null)
    {
        // Get the underlying collection
        $collection = $paginator->getCollection();

        // Assign the collection as our resource, and bridge the paginator so that we can
        // include pagination meta-data
        $this->smokescreen->collection($collection, $transformer, null,
            function (\Rexlabs\Smokescreen\Resource\Collection $resource) use ($paginator) {
                $resource->setPaginator(new PaginatorBridge($paginator));
            });

        return $this;
    }

    /**
     * Set the transformer used to transform the resource(s).
     * Proxies to the underlying \Rexlabs\Smokescreen instance.
     * @param TransformerInterface|callable $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     */
    public function transformWith($transformer)
    {
        $this->smokescreen->setTransformer($transformer);

        return $this;
    }

    /**
     * Override the serializer.
     * Proxies to the underlying \Rexlabs\Smokescreen instance.
     * @param SerializerInterface $serializer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     */
    public function serializeWith($serializer)
    {
        $this->serializer = $serializer;

        return $this;
    }

    /**
     * Set the relationship loader.
     * The relationship loader takes the relationships defined on a transformer, and eager-loads them.
     * @param RelationLoaderInterface $relationLoader
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function loadRelationsVia(RelationLoaderInterface $relationLoader)
    {
        $this->smokescreen->setRelationLoader($relationLoader);

        return $this;
    }

    /**
     * Returns an object representation of the transformed/serialized data.
     * @return \stdClass
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function toObject(): \stdClass
    {
        return json_decode($this->toJson(), false);
    }

    /**
     * Outputs a JSON string of the resulting transformed and serialized data.
     * Implements Laravel's Jsonable interface.
     * @param int $options
     * @return string
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function toJson($options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonEncodingException(json_last_error_msg());
        }

        return $json;
    }

    /**
     * Output the transformed and serialized data as an array.
     * Implements PHP's JsonSerializable interface.
     * @return array
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     * @see Smokescreen::toArray()
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Output the transformed and serialized data as an array.
     * This kicks off the transformation via the base Smokescreen object.
     * @return array
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     */
    public function toArray(): array
    {
        $resource = $this->smokescreen->getResource();
        if ($resource !== null && !$resource->hasTransformer()) {
            // We have a resource, but it does not have a transformer assigned
            // so we will try to resolve one based on the model and our config
            $resource->setTransformer($this->resolveTransformerForResource($resource));
        }

        // Serializer may be overridden via config
        $serializer = $this->serializer ?? app('config')->get('smokescreen.default_serializer', null);

        // We may be setting the serializer to null, in which case Smokescreen will use its default.
        $this->smokescreen->setSerializer($serializer);

        if ($this->includes) {
            // Includes have been set explicitly
            $this->smokescreen->parseIncludes($this->includes);
        } elseif ($this->autoParseIncludes) {
            // If autoParseIncludes is not false, then try to parse from the request object
            $this->smokescreen->parseIncludes($this->request()->input($this->getIncludeKey()));
        }

        if (!$this->smokescreen->hasRelationLoader()) {
            // No relation loader has been set, so provide the default loader
            $this->smokescreen->setRelationLoader(new RelationLoader());
        }

        return $this->smokescreen->toArray();
    }

    /**
     * Determines the Transformer object to be used for a particular resource.
     * Inspects the underlying Eloquent model to determine an appropriately
     * named transformer class, and instantiate the object.
     *
     * @param ResourceInterface $resource
     *
     * @return TransformerInterface|callable|null
     * @throws UnresolvedTransformerException
     */
    public function resolveTransformerForResource(ResourceInterface $resource)
    {
        // When a transformer is explicitly set on the resource, return it.
        if (($transformer = $resource->getTransformer()) !== null) {
            return $transformer;
        }

        // Otherwise, inspect the resource data ...
        $data = $resource->getData();
        $model = null;
        if ($data instanceof Model) {
            $model = $data;
        } elseif ($data instanceof Builder) {
            $model = $data->getModel();
        } elseif ($data instanceof Collection) {
            $model = $data->first();
        } elseif ($data instanceof Paginator) {
            $model = \count($data) > 0 ? $data[0] : null;
        }

        if ($model === null) {
            // Don't assign any transformer for this data
            return null;
        }

        if (!($model instanceof Model)) {
            throw new UnresolvedTransformerException('Cannot determine a valid Model for resource');
        }

        try {
            $transformerClass = sprintf('%s\\%sTransformer',
                app('config')->get('smokescreen.transformer_namespace', 'App\\Transformers'),
                (new \ReflectionClass($model))->getShortName());

            $transformer = app()->make($transformerClass);
        } catch (\Exception $e) {
            throw new UnresolvedTransformerException('Unable to resolve transformer for model: ' . \get_class($model). 0, $e);
        }

        return $transformer;
    }

    /**
     * Get a Laravel request object.  If not set explicitly via setRequest(...) then
     * it will be automatically resolved out of the container. You're welcome.
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
     * otherwise, the 'include_key' from the config/smokescreen.php.
     * Defaults to 'include'
     * @return string
     */
    protected function getIncludeKey(): string
    {
        $key = \is_string($this->autoParseIncludes) ? $this->autoParseIncludes : config('smokescreen.include_key',
            'include');

        return $key;
    }

    /**
     * Generates a Response object.
     * Implements Laravel's Responsable contract, so that you can return smokescreen object from a controller.
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
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
     * @param int   $statusCode
     * @param array $headers
     * @param int   $options
     * @return \Illuminate\Http\JsonResponse
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
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
     * See the response() method
     * @param int   $statusCode
     * @param array $headers
     * @param int   $options
     * @return \Illuminate\Http\JsonResponse
     * @throws \Rexlabs\Smokescreen\Exception\InvalidTransformerException
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     * @see Smokescreen::toArray()
     * @see Smokescreen::response()
     */
    public function freshResponse(int $statusCode = 200, array $headers = [], int $options = 0): JsonResponse
    {
        $this->clearResponse();

        return $this->response($statusCode, $headers, $options);
    }

    /**
     * Clear the cached response object
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function clearResponse()
    {
        $this->response = null;

        return $this;
    }

    /**
     * Apply a callback to the response.  The response will be generated if it has not already been.
     * @param callable $apply
     * @return $this|\Illuminate\Contracts\Support\Responsable
     * @throws \Rexlabs\Smokescreen\Exception\MissingResourceException
     */
    public function withResponse(callable $apply)
    {
        $apply($this->response());

        return $this;
    }

    /**
     * Set the include string.
     * @param string|null $includes
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function include($includes)
    {
        $this->includes = $includes === null ? $includes : (string)$includes;

        return $this;
    }

    /**
     * Disable all includes
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
     * @param \Illuminate\Http\Request $request
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;

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
}
