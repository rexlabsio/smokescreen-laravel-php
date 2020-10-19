<?php

namespace Rexlabs\Laravel\Smokescreen\Tests;

class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup code
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set(
            'database.connections.testbench',
            [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            ]
        );
    }
}
