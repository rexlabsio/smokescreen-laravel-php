<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\View\Factory;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class MakeTransformerCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:transformer {model : The namespaced model to transform. e.g. "App\User"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new transformer class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Transformer';

    /**
     * The view factory.
     *
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $factory;

    /**
     * The includes listing.
     *
     * @var \Rexlabs\Laravel\Smokescreen\Console\IncludesListing
     */
    protected $includesListing;

    /**
     * The properties listing.
     *
     * @var \Rexlabs\Laravel\Smokescreen\Console\PropertiesListing
     */
    protected $propertiesListing;

    /**
     * Set the dependencies.
     *
     * @param  \Illuminate\Filesystem\Filesystem $files
     * @param  \Illuminate\Contracts\View\Factory $factory
     * @param  \Rexlabs\Laravel\Smokescreen\Console\IncludesListing $includesListing
     * @param  \Rexlabs\Laravel\Smokescreen\Console\PropertiesListing $propertiesListing
     */
    public function __construct(
        Filesystem $files,
        Factory $factory,
        IncludesListing $includesListing,
        PropertiesListing $propertiesListing
    ) {
        parent::__construct($files);

        $this->factory = $factory;
        $this->includesListing = $includesListing;
        $this->propertiesListing = $propertiesListing;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return 'smokescreen::transformer';
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     */
    public function handle()
    {
        $this->alertIfModelIsMissing();

        parent::handle();
    }

    /**
     * Display an error if the specified model does not exist.
     *
     */
    protected function alertIfModelIsMissing()
    {
        if (!class_exists($name = $this->argument('model'))) {
            exit($this->error("The model [$name] does not exist, please create it first."));
        }
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return config('smokescreen.transformer_namespace');
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $model = $this->argument('model');

        $data = [
            'namespace' => $this->getNamespace($name),
            'modelName' => $this->getModelName(),
            'transformerName' => $this->getNameInput(),
            'includes' => $this->includesListing->listForEloquent($model),
            'properties' => $this->propertiesListing->listForEloquent($model),
        ];

        return $this->factory->make($this->getStub(), $data)->render();
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return $this->getModelName() . 'Transformer';
    }

    /**
     * Retrieve the model name.
     *
     * @return string
     */
    protected function getModelName() : string
    {
        return class_basename($this->argument('model'));
    }
}
