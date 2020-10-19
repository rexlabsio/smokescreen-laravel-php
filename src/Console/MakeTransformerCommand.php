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
     * make:transformer User
     *
     * @var string
     */
    protected $signature = 'make:transformer
        {model? : The name of the model to transform. e.g. User} 
        {-f|--force : Overwrite an existing transformer}
        {-d|--directory= : Specify the models directory }
        {-a|--all : Generate a transformer for all models. }';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new smokescreen transformer class';

    /**
     * The view factory.
     *
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $viewFactory;

    /**
     * Inject the dependencies.
     *
     * @param \Illuminate\Filesystem\Filesystem  $files
     * @param \Illuminate\Contracts\View\Factory $viewFactory
     */
    public function __construct(
        Filesystem $files,
        Factory $viewFactory
    ) {
        parent::__construct($files);

        $this->viewFactory = $viewFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function handle()
    {
        $model = $this->getModelInput();
        $this->type = "{$model} transformer";

        if ($this->option('all')) {
            $this->line('Generating all transformers');
            $this->generateAllTransformers();
            return;
        }

        if (empty($model)) {
            $this->error('Must specify a model or the --all option');
            return;
        }

        // TODO: Given a models directory like app/ determine the namespace
        if (!class_exists($modelClass = $this->getModelClass())) {
            $this->error("The model [{$modelClass}] does not exist.");
            return;
        }

        if (!$this->option('force') && class_exists($this->getTransformerClass())) {
            $this->warn("{$this->type} already exists.");
            return;
        }


        parent::handle();
    }

    /**
     * Generate a transformer for every model.
     */
    protected function generateAllTransformers()
    {
        $directory = $this->getModelDirectory();
        $models = (new ModelsFinder())->findInDirectory($directory);

        foreach ($models as $model) {
            $this->call(
                'make:transformer',
                [
                'model' => $model,
                '--force' => $this->option('force'),
                ]
            );
        }
    }

    /**
     * Retrieve the models directory.
     *
     * @return string
     */
    protected function getModelDirectory(): string
    {
        $relativePath = $this->option('directory') ?: config('smokescreen.models_directory', 'app');

        if (!file_exists($absolutePath = base_path($relativePath))) {
            $this->error("The specified models directory does not exist: {$absolutePath}");
            exit();
        }

        return $absolutePath;
    }

    /**
     * {@inheritdoc}
     */
    protected function getStub()
    {
        return 'smokescreen::transformer';
    }

    /**
     * {@inheritdoc}
     */
    protected function buildClass($name)
    {
        return $this->viewFactory->make($this->getStub(), $this->getTemplateData())
            ->render();
    }

    /**
     * @throws \ReflectionException
     *
     * @return array
     */
    protected function getTemplateData()
    {
        $modelInspector = new ModelMapper($this->getModel());

        return [
            'rootNamespace' => $this->rootNamespace(),
            'model' => $this->getModel(),
            'modelClass' => $this->getModelClass(),
            'modelNamespace' => $this->getModelNamespace(),
            'modelName' => $this->getModelName(),
            'transformerClass' => $this->getTransformerClass(),
            'transformerNamespace' => $this->getTransformerNamespace(),
            'transformerName' => $this->getTransformerName(),
            'includes' => $modelInspector->getIncludes(),
            'properties' => $modelInspector->getDeclaredProperties(),
            'defaultProperties' => $modelInspector->getDefaultProperties(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getNameInput()
    {
        return $this->getTransformerClass();
    }

    /**
     * Get the transformer class name.
     *
     * @return string
     */
    protected function getTransformerName()
    {
        return preg_replace(
            '/{ModelName}/i',
            $this->getModelName(),
            config('smokescreen.transformer_name', '{ModelName}Transformer')
        );
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
        $class = $this->getModelInput();
        if (!str_contains($class, '\\')) {
            $directory = $this->getModelDirectory();
            $file = str_finish($directory, '/') . $this->getModelInput();
            $segments = array_map('ucfirst', explode('/', $file));

            $class = implode('\\', $segments);

            // Make relevant to the root namespace
            if (($pos = strpos($class, $this->rootNamespace())) !== false) {
                $class = substr($class, $pos);
            }
        }

        return $class;
    }

    /**
     * Get the eloquent model instance.
     *
     * @return Model
     */
    protected function getModel(): Model
    {
        $class = $this->getModelClass();

        return new $class();
    }

    /**
     * Retrieve the model class name.
     *
     * @return string
     */
    protected function getModelInput(): string
    {
        return ucfirst($this->argument('model'));
    }

    /**
     * Retrieve the model class name.
     *
     * @return string
     */
    protected function getModelName(): string
    {
        return class_basename($this->getModelInput());
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
