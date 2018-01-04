<?php
namespace RexSoftware\Laravel\Smokescreen\Facades;

use RexSoftware\Laravel\Smokescreen\Smokescreen;

class Facade extends \Illuminate\Support\Facades\Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor()
    {
        return Smokescreen::class;
    }
}