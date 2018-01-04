<?php
namespace RexSoftware\Laravel\Smokescreen\Relations;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use RexSoftware\Smokescreen\Relations\RelationLoaderInterface;
use RexSoftware\Smokescreen\Resource\ResourceInterface;

/**
 * Laravel implementation for loading relationships.
 * Smokescreen will call this automatically for all resources.
 * @package RexSoftware\Laravel\Smokescreen\Relations
 */
class RelationLoader implements RelationLoaderInterface
{
    public function load(ResourceInterface $resource)
    {
        // Eager load relationships on collections
        $obj = $resource->getData();
        if ($obj instanceof Collection || $obj instanceof Paginator) {
            $obj->load($resource->getRelationships());
        }
    }
}