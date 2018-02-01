<?php

namespace RexSoftware\Laravel\Smokescreen;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use RexSoftware\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException;
use RexSoftware\Laravel\Smokescreen\Pagination\Paginator as PaginatorBridge;
use RexSoftware\Laravel\Smokescreen\Relations\RelationLoader;
use RexSoftware\Laravel\Smokescreen\Transformers\EmptyTransformer;
use RexSoftware\Smokescreen\Relations\RelationLoaderInterface;
use RexSoftware\Smokescreen\Resource\ResourceInterface;
use RexSoftware\Smokescreen\Serializer\SerializerInterface;
use RexSoftware\Smokescreen\Transformer\TransformerInterface;

/**
 * Laravel Smokescreen.
 * Tightly integrates the rexsoftware/smokescreen resource transformation library with the Laravel framework.
 * @package RexSoftware\Laravel\Smokescreen
 * @author Jodie Dunlop <jodie.dunlop@rexsoftware.com.au>
 * @copyright Rex Software 2018
 */
class Smokescreen implements \JsonSerializable, Jsonable, Arrayable, Responsable
{
    /** @var \RexSoftware\Smokescreen\Smokescreen */
    protected $smokescreen;

    /** @var string|null */
    protected $includes;

    /** @var string|bool Whether includes should be parsed from a request key */
    protected $autoParseIncludes = true;

    /** @var Request|null */
    protected $request;

    /** @var Response|null */
    protected $response;

    public function __construct(\RexSoftware\Smokescreen\Smokescreen $smokescreen)
    {
        $this->smokescreen = $smokescreen;
    }

    /**
     * Creates a new Smokescreen object
     * @return static
     */
    public static function make()
    {
        return new static(new \RexSoftware\Smokescreen\Smokescreen());
    }

    /**
     * Set the resource (item or collection) data to be transformed.
     * You should pass in an instance of a Model.
     * @param mixed|Model|array $data
     * @param callable|TransformerInterface|null $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function transform($data, $transformer = null)
    {
        if ($data instanceof Model) {
            $this->item($data, $transformer);
        } else {
            // Assume everything else is a collection
            $this->collection($data, $transformer);
        }

        return $this;
    }

    /**
     * Set an item resource to be transformed.
     * @param mixed $data
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
     * @param mixed $data
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
     * @param callable|TransformerInterface|null $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     */
    public function paginate(LengthAwarePaginator $paginator, $transformer = null)
    {
        // Get the underlying collection
        $collection = $paginator->getCollection();

        // Assign the collection as our resource, and bridge the paginator so that we can
        // include pagination meta-data
        $this->smokescreen->collection($collection, $transformer, null,
            function (\RexSoftware\Smokescreen\Resource\Collection $resource) use ($paginator) {
                $resource->setPaginator(new PaginatorBridge($paginator));
            }
        );

        return $this;
    }

    /**
     * Set the transformer used to transform the resource(s).
     * Proxies to the underlying \RexSoftware\Smokescreen instance.
     * @param TransformerInterface|callable $transformer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
     */
    public function transformWith($transformer)
    {
        $this->smokescreen->setTransformer($transformer);

        return $this;
    }

    /**
     * Set the serializer.
     * Proxies to the underlying \RexSoftware\Smokescreen instance.
     * @param SerializerInterface $serializer
     * @return $this|\Illuminate\Contracts\Support\Responsable
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
     */
    public function serializeWith($serializer)
    {
        $this->smokescreen->setSerializer($serializer);

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
     * Outputs a JSON string of the resulting transformed and serialized data.
     * Implements Laravel's Jsonable interface.
     * @param int $options
     * @return string
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
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
     * Returns an object representation of the transformed/serialized data.
     * @return \stdClass
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
     * @throws \Illuminate\Database\Eloquent\JsonEncodingException
     */
    public function toObject(): \stdClass
    {
        return json_decode($this->toJson(), false);
    }

    /**
     * Output the transformed and serialized data as an array.
     * Implements PHP's JsonSerializable interface.
     * @return array
     * @throws \RexSoftware\Smokescreen\Exception\InvalidTransformerException
     * @throws \RexSoftware\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
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
     * @throws \RexSoftware\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \RexSoftware\Smokescreen\Exception\InvalidTransformerException
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
     */
    public function toArray(): array
    {
        $resource = $this->smokescreen->getResource();
        if ($resource !== null && !$resource->hasTransformer()) {
            // We have a resource, but it does not have a transformer assigned
            // so we will try to resolve one based on the model and our config
            $resource->setTransformer($this->resolveTransformerForResource($resource));
        }

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
     * @param ResourceInterface $resource
     * @return TransformerInterface|null
     * @throws \RexSoftware\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     */
    protected function resolveTransformerForResource(ResourceInterface $resource)
    {
        $data = $resource->getData();

        $model = null;
        if ($data instanceof Model) {
            $model = $data;
        } elseif ($data instanceof Builder) {
            $model = $data->getModel();
        } elseif ($data instanceof Collection) {
            $model = $data->first();
        } elseif ($data instanceof Paginator) {
            $model = \count($data) > 0 ?
                $data[0] :
                null;
        }

        if ($model && !$model instanceof Model) {
            throw new UnresolvedTransformerException('Cannot determine a valid Model for resource');
        }

        if (!$model) {
            return app()->make(EmptyTransformer::class);
        }

        $transformerClass = sprintf('%s\\%sTransformer',
            config('smokescreen.transformer_namespace', 'App\Transformers'),
            (new \ReflectionClass($model))->getShortName()
        );

        return app()->make($transformerClass);
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
        $key = \is_string($this->autoParseIncludes) ?
            $this->autoParseIncludes :
            config('smokescreen.include_key', 'include');

        return $key;
    }

    /**
     * Generates a Response object.
     * Implements Laravel's Responsable contract, so that you can return smokescreen object from a controller.
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     * @throws \RexSoftware\Smokescreen\Exception\InvalidTransformerException
     * @throws \RexSoftware\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
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
     * @param int $statusCode
     * @param array $headers
     * @param int $options
     * @return \Illuminate\Http\JsonResponse
     * @throws \RexSoftware\Smokescreen\Exception\InvalidTransformerException
     * @throws \RexSoftware\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
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
     * @param int $statusCode
     * @param array $headers
     * @param int $options
     * @return \Illuminate\Http\JsonResponse
     * @throws \RexSoftware\Smokescreen\Exception\InvalidTransformerException
     * @throws \RexSoftware\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
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
     * @throws \RexSoftware\Smokescreen\Exception\MissingResourceException
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
     * @return \RexSoftware\Smokescreen\Smokescreen
     */
    public function getBaseSmokescreen(): \RexSoftware\Smokescreen\Smokescreen
    {
        return $this->smokescreen;
    }
}
