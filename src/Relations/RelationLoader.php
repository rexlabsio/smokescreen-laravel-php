<?php

namespace Rexlabs\Laravel\Smokescreen\Relations;

use Illuminate\Database\Eloquent\Collection;
use Rexlabs\Smokescreen\Relations\RelationLoaderInterface;
use Rexlabs\Smokescreen\Resource\ResourceInterface;

/**
 * Laravel implementation for loading relationships.
 * Smokescreen will call this automatically for all resources.
 */
class RelationLoader implements RelationLoaderInterface
{
    public function load(ResourceInterface $resource, array $relationshipKeys)
    {
        // Eager load relationships on collections
        $obj = $resource->getData();
        if ($obj instanceof Collection) {
            $obj->loadMissing($relationshipKeys);
        }
    }
}
