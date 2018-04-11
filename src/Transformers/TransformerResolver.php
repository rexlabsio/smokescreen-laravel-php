<?php

namespace Rexlabs\Laravel\Smokescreen\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException;
use Rexlabs\Smokescreen\Resource\ResourceInterface;
use Rexlabs\Smokescreen\Transformer\TransformerResolverInterface;

class TransformerResolver implements TransformerResolverInterface
{
    /** @var string|null */
    protected $namespace;

    public function __construct($namespace = null)
    {
        $this->namespace = $namespace;
    }

    /**
     * Determines the Transformer object to be used for a particular resource.
     * Inspects the underlying Eloquent model to determine an appropriately
     * named transformer class, and instantiate the object.
     *
     * @inheritdoc
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     */
    public function resolve(ResourceInterface $resource)
    {
        $transformer = null;

        dump('RESOLVE TIME for '.get_class($resource));

        // Find the underlying model of the resource data
        $model = null;
        $data = $resource->getData();
        dump(get_class($data));
        if ($data instanceof Model) {
            $model = $data;
        } elseif ($data instanceof Collection) {
            $model = $data->first();
        }

        // If no model can be determined from the data
        if ($model !== null) {
            // Cool, now let's try to find a matching transformer based on our Model class
            // We use our configuration value 'transformer_namespace' to determine where to look.
            try {
                $transformerClass = sprintf('%s\\%sTransformer',
                    $this->namespace ?? 'App\\Transformers',
                    (new \ReflectionClass($model))->getShortName());
                $transformer = resolve($transformerClass);
            } catch (\Exception $e) {
                throw new UnresolvedTransformerException('Unable to resolve transformer for model: ' . \get_class($model),
                    0, $e);
            }
        }

        return $transformer;
    }
}