<?php

namespace Rexlabs\Laravel\Smokescreen\Providers;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Rexlabs\Laravel\Smokescreen\Smokescreen;

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
        $this->app->singleton(Smokescreen::class, function() {
            return new Smokescreen(new \Rexlabs\Smokescreen\Smokescreen(), config('smokescreen', [ ]));
        });
        $this->app->alias(Smokescreen::class, 'smokescreen');
    }
}
