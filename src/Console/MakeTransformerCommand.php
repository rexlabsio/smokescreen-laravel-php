<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;

class MakeTransformerCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     * smokescreen:transformer --for='App\Models\Post'
     *
     * @var string
     */
    protected $signature = 'make:transformer {model : The model to transform. e.g. "App\User"}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new smokescreen transformer class';

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
    protected $viewFactory;

    /**
     * @var string
     */
    protected $modelClass;

    /**
     * Inject the dependencies.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @param  \Illuminate\Contracts\View\Factory $viewFactory
     */
    public function __construct(
        Filesystem $files,
        Factory $viewFactory
    ) {
        parent::__construct($files);

        $this->viewFactory = $viewFactory;
    }

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $this->modelClass = $this->resolveModelClass($this->argument('model'));

        parent::handle();
    }

    /**
     * @inheritdoc
     */
    protected function getStub()
    {
        return 'smokescreen::transformer';
    }

    /**
     * Given a model name (or namespace) try to resolve the fully qualified model class
     * while checking common model namespaces.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    protected function resolveModelClass(string $name)
    {
        $modelClass = null;

        // If not name-spaced, get a list of classes to search in common model namespaces.
        $search = str_contains('\\', $name) ? [$name] : array_map(function ($directory) use ($name) {
            return $directory . '\\' . $name;
        }, ['App\\Models', 'App\\Model', 'App']);

        // Check for a valid class.
        foreach ($search as $class) {
            if (class_exists($class)) {
                $modelClass = $class;
                break;
            }
        }

        // If we didn't find one, exit out.
        if ($modelClass === null) {
            throw new \InvalidArgumentException("The model [$name] does not exist, please create it first.");
        }

        return $modelClass;
    }

    /**
     * @inheritdoc
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $this->getTransformerNamespace();
    }

    /**
     * @inheritdoc
     */
    protected function buildClass($name)
    {
        $modelInspector = new ModelInspector($this->getModel());
        $data = [
            'transformerNamespace' => $this->getTransformerNamespace(),
            'transformerName'      => $this->getTransformerName(),
            'model'                => $this->getModel(),
            'modelClass'           => $this->getModelClass(),
            'modelNamespace'       => $this->getModelNamespace(),
            'modelName'            => $this->getModelName(),
            'includes'             => $modelInspector->getIncludes(),
            'properties'           => $modelInspector->getDeclaredProperties(),
            'defaultProperties'    => $modelInspector->getDefaultProperties(),
        ];

        return $this->viewFactory->make($this->getStub(), $data)
            ->render();
    }

    /**
     * @inheritdoc
     */
    protected function getNameInput()
    {
        return $this->getTransformerName();
    }

    /**
     * Get the transformer class name.
     *
     * @return string
     */
    protected function getTransformerName()
    {
        return $this->getModelName() . 'Transformer';
    }

    /**
     * Retrieve the transformer namespace.
     *
     * @return \Illuminate\Config\Repository|mixed
     */
    protected function getTransformerNamespace()
    {
        return config('smokescreen.transformer_namespace', 'App\Transformers');
    }

    /**
     * Retrieve the transformer class including namespace.
     *
     * @return string
     */
    protected function getTransformerClass()
    {
        return $this->getTransformerNamespace() . '\\' . $this->getTransformerName();
    }

    /**
     * Retrieve the model class including namespace.
     *
     * @return string
     */
    protected function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * Get the eloquent model instance.
     *
     * @return Model
     */
    protected function getModel(): Model
    {
        $class = $this->getModelClass();

        return new $class;
    }

    /**
     * Retrieve the model class name.
     *
     * @return string
     */
    protected function getModelName(): string
    {
        return class_basename($this->getModelClass());
    }

    /**
     * Get the namespace of the model class.
     *
     * @return string
     */
    protected function getModelNamespace()
    {
        return $this->getNamespace($this->getModelClass());
    }
}
