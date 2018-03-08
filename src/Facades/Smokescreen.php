<?php

namespace Rexlabs\Laravel\Smokescreen\Facades;

/**
 * Class Smokescreen.
 *
 * @method static \Rexlabs\Laravel\Smokescreen\Smokescreen transform(mixed|\Illuminate\Database\Eloquent\Model|array $data, mixed|null $transformer = null)
 */
class Smokescreen extends \Illuminate\Support\Facades\Facade
{
    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return \Rexlabs\Laravel\Smokescreen\Smokescreen::class;
    }
}
