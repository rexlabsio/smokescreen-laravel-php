<?php
namespace Rexlabs\Laravel\Smokescreen\Helpers;

trait TransformHelper
{
    public function when($condition, $whenTrue, $whenFalse = null)
    {
        return $condition ? $whenTrue : $whenFalse;
    }
}