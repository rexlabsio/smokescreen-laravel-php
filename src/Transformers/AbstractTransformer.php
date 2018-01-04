<?php
namespace RexSoftware\Laravel\Smokescreen\Transformers;

use RexSoftware\Laravel\Smokescreen\Helpers\TransformHelper;

/**
 * Add some additional helpers to your transformers.
 * Extend this class, or simply include the TransformHelper trait.
 * @package RexSoftware\Laravel\Smokescreen\Transformers
 */
class AbstractTransformer extends \RexSoftware\Smokescreen\Transformer\AbstractTransformer
{
    use TransformHelper;
}