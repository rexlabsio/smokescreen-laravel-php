<?php
/**
 * laravel-smokescreen
 *
 * User: rhys
 * Date: 1/2/18
 * Time: 11:53 AM
 */

namespace RexSoftware\Laravel\Smokescreen\Transformers;


class EmptyTransformer extends AbstractTransformer
{
    public function transform($data = null)
    {
        return [];
    }
}