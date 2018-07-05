<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class ModelsFinder
{
    /**
     * Find the models in the given directory.
     *
     * @param string $directory
     *
     * @return array|string[]
     * @throws \ReflectionException
     */
    public function findInDirectory(string $directory): array
    {
        $models = [];
        $iterator = Finder::create()->files()->name('*.php')->in($directory)->depth(0)->sortByName();

        foreach ($iterator as $file) {
            $class = $this->determineClassFromFile($file);
            if ($class !== null && class_exists($class) && $this->isModelClass($class)) {
                $models[] = $class;
            }
        }

        return $models;
    }

    /**
     * Retrieve the fully-qualified class name of the given file.
     *
     * @param SplFileInfo $file
     *
     * @return string|null
     */
    protected function determineClassFromFile(SplFileInfo $file)
    {
        if (!preg_match('/namespace (.*);/', $file->getContents(), $matches)) {
            return null;
        }

        return $matches[1] . '\\' . rtrim($file->getFilename(), '.php');
    }

    /**
     * Determine if the given class is an eloquent model.
     *
     * @param string $class
     *
     * @return bool
     * @throws \ReflectionException
     */
    protected function isModelClass(string $class): bool
    {
        if (!is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
            // Must extend Model
            return false;
        }

        if ((new ReflectionClass($class))->isAbstract()) {
            // Exclude abstract classes
            return false;
        }

        return true;
    }
}
