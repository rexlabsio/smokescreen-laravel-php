<?php

namespace RexSoftware\Laravel\Smokescreen\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use RexSoftware\Laravel\Smokescreen\Smokescreen;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../../config/smokescreen.php' => config_path('smokescreen.php'),
        ], 'config');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(Smokescreen::class, function () {
            return new Smokescreen(new \RexSoftware\Smokescreen\Smokescreen());
        });
        $this->app->alias(Smokescreen::class, 'smokescreen');
    }
}
