<?php
namespace Rexlabs\Laravel\Smokescreen\Transformers;

use Rexlabs\Laravel\Smokescreen\Helpers\TransformHelper;

/**
 * Add some additional helpers to your transformers.
 * Extend this class, or simply include the TransformHelper trait.
 * @package Rexlabs\Laravel\Smokescreen\Transformers
 */
class AbstractTransformer extends \Rexlabs\Smokescreen\Transformer\AbstractTransformer
{
    use TransformHelper;
}