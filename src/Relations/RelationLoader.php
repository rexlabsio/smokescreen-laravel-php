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
            $obj->load($resource->getRelationships());
        }
    }
}