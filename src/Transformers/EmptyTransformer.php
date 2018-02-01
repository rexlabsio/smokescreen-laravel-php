<?php

namespace RexSoftware\Laravel\Smokescreen\Transformers;

/**
 * Class EmptyTransformer. Used in the case where an empty resource collection is provided.
 *
 * @package RexSoftware\Laravel\Smokescreen\Transformers
 */
class EmptyTransformer extends AbstractTransformer
{
    public function transform($data = null)
    {
        return [];
    }
}