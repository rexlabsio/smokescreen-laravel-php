<?php

namespace Rexlabs\Laravel\Smokescreen\Relations;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Rexlabs\Smokescreen\Relations\RelationLoaderInterface;
use Rexlabs\Smokescreen\Resource\ResourceInterface;

/**
 * Laravel implementation for loading relationships.
 * Smokescreen will call this automatically for all resources.
 */
class RelationLoader implements RelationLoaderInterface
{
    public function load(ResourceInterface $resource)
    {
        // Eager load relationships on collections
        $resourceData = $resource->getData();
        if ($resourceData instanceof Collection) {
            $keys = $this->getRelationshipKeys($resource);
            if (!empty($keys)) {
                $resourceData->load($keys);
            }
        }
    }

    /**
     * Return all the unique relationship keys for a resource.
     *
     * @param ResourceInterface $resource
     *
     * @return array
     */
    protected function getRelationshipKeys(ResourceInterface $resource): array
    {
        $keys = [];
        foreach ($resource->getRelationships() as $key => $relationships) {
            if (!empty($relationships)) {
                array_push($keys, ...$relationships);
            }
        }

        return array_unique($keys);
    }
}
