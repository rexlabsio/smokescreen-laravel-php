<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use Illuminate\Console\GeneratorCommand;

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
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/transformer.stub';
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     */
    public function handle()
    {
        if (!class_exists($name = $this->argument('model'))) {
            $confirmed = $this->confirm(
                'The model to transform does not exist, do you want to generate it?'
            );

            if ($confirmed) {
                $this->call('make:model', compact('name'));
            }
        }

        parent::handle();
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
        $stub = parent::buildClass($name);

        return $this->replaceModel($stub);
    }

    /**
     * Retrieve the given content replacing the model in it.
     *
     * @param string $content
     * @return string
     */
    protected function replaceModel(string $content) : string
    {
        $from = [
            'DummyModelName',
            'dummyModelName',
            'DummyModel',
        ];

        $to = [
            $this->getModelName(),
            lcfirst($this->getModelName()),
            $this->argument('model'),
        ];

        return str_replace($from, $to, $content);
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
