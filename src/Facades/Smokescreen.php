<?php
namespace RexSoftware\Laravel\Smokescreen\Facades;

class Smokescreen extends \Illuminate\Support\Facades\Facade
{
    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor()
    {
        return \RexSoftware\Laravel\Smokescreen\Smokescreen::class;
    }
}
