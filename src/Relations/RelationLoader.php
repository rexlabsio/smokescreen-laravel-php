<?php
namespace Rexlabs\Laravel\Smokescreen\Relations;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Rexlabs\Smokescreen\Relations\RelationLoaderInterface;
use Rexlabs\Smokescreen\Resource\ResourceInterface;

/**
 * Laravel implementation for loading relationships.
 * Smokescreen will call this automatically for all resources.
 * @package Rexlabs\Laravel\Smokescreen\Relations
 */
class RelationLoader implements RelationLoaderInterface
{
    public function load(ResourceInterface $resource)
    {
        // Eager load relationships on collections
        $obj = $resource->getData();
        if ($obj instanceof Collection || $obj instanceof Paginator) {
            $keys = $this->getRelationshipKeys($resource);
            if (!empty($keys)) {
                $obj->load($keys);
            }
        }
    }

    /**
     * Return all the unique relationship keys for a resource
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