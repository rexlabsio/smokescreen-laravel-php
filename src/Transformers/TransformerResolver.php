<?php

namespace Rexlabs\Laravel\Smokescreen\Transformers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException;
use Rexlabs\Smokescreen\Resource\ResourceInterface;
use Rexlabs\Smokescreen\Transformer\TransformerResolverInterface;

class TransformerResolver implements TransformerResolverInterface
{
    /** @var string */
    protected $namespace;

    /** @var string */
    protected $nameTemplate;

    /**
     * TransformerResolver constructor.
     *
     * @param string $namespace
     * @param string $nameTemplate
     */
    public function __construct(string $namespace, string $nameTemplate)
    {
        $this->namespace = $namespace;
        $this->nameTemplate = $nameTemplate;
    }

    /**
     * Determines the Transformer object to be used for a particular resource.
     * Inspects the underlying Eloquent model to determine an appropriately
     * named transformer class, and instantiate the object.
     *
     * {@inheritdoc}
     *
     * @throws \Rexlabs\Laravel\Smokescreen\Exceptions\UnresolvedTransformerException
     */
    public function resolve(ResourceInterface $resource)
    {
        $transformer = null;

        // Find the underlying model of the resource data
        $model = null;
        $data = $resource->getData();
        if ($data instanceof Model) {
            $model = $data;
        } elseif ($data instanceof Collection) {
            $model = $data->first();
        }

        if ($model !== null) {
            // Cool, now let's try to find a matching transformer based on our Model class
            // We use our configuration value 'transformer_namespace' to determine where to look.
            try {
                $modelName = (new \ReflectionClass($model))->getShortName();
                $transformerName = preg_replace('/{ModelName}/i', $modelName, $this->nameTemplate);
                $transformer = resolve(sprintf('%s\\%s', $this->namespace, $transformerName));
            } catch (\Exception $e) {
                throw new UnresolvedTransformerException('Unable to resolve transformer for model: '.\get_class($model),
                    0, $e);
            }
        }

        return $transformer;
    }
}
