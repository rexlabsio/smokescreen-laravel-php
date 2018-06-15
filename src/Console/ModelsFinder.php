<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * The models finder.
 */
class ModelsFinder
{
    /**
     * Find the models in the given directory.
     *
     * @param string $directory
     *
     * @return array
     */
    public function findInDirectory(string $directory) : array
    {
        $models = [];
        $iterator = Finder::create()->files()->name('*.php')->in($directory)->depth(0)->sortByName();

        foreach ($iterator as $file) {
            if (!class_exists($class = $this->qualifyClassFromFile($file))) {
                continue;
            }

            $isAbstract = (new ReflectionClass($class))->isAbstract();
            $isModel = is_subclass_of($class, 'Illuminate\Database\Eloquent\Model');

            if ($isModel && !$isAbstract) {
                $models[] = new $class();
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
    protected function qualifyClassFromFile(SplFileInfo $file) : ?string
    {
        preg_match('/namespace (.*);/', $file->getContents(), $matches);

        if (is_null($namespace = $matches[1] ?? null)) {
            return null;
        }

        return $namespace.'\\'.rtrim($file->getFilename(), '.php');
    }
}
