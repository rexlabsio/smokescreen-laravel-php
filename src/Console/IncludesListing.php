<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use ReflectionClass;
use ReflectionMethod;

/**
 * The includes listing.
 *
 */
class IncludesListing
{
    /**
     * The map of available relations.
     *
     * @var array
     */
    protected $relationsMap = [
        'hasOne' => 'item',
        'morphOne' => 'item',
        'belongsTo' => 'item',
        'morphTo' => 'item',
        'hasMany' => 'collection',
        'hasManyThrough' => 'collection',
        'morphMany' => 'collection',
        'belongsToMany' => 'collection',
        'morphToMany' => 'collection',
        'morphedByMany' => 'collection',
    ];

    /**
     * List the includes of the given Eloquent model.
     *
     * @param string $class
     * @return array
     */
    public function listForEloquent(string $class) : array
    {
        $list = [];
        $methods = (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC);
        $declaredMethods = array_filter($methods, function ($method) use ($class) {
            return $method->getDeclaringClass()->getName() == $class;
        });

        foreach ($declaredMethods as $method) {
            if ($type = $this->getResourceTypeByMethodReturnType($method)) {
                $list[$method->getName()] = "relation|{$type}";
            } elseif ($type = $this->getResourceTypeByMethodDefinition($method)) {
                $list[$method->getName()] = "relation|{$type}";
            }
        }

        return $list;

        // implement getResourceTypeByMethodDefinition
        // investigate custom relation names with actual relation name
        // ^ if different may not relate on $method->getName()
    }

    /**
     * Retrieve the type of the resource based on the given method return type.
     *
     * @param ReflectionMethod $method
     * @return ?string
     */
    protected function getResourceTypeByMethodReturnType(ReflectionMethod $method) : ?string
    {
        $returnType = (string) $method->getReturnType();
        $namespace = 'Illuminate\Database\Eloquent\Relations';

        if (!starts_with($returnType, $namespace)) {
            return null;
        }

        $relation = lcfirst(class_basename($returnType));

        return $this->relationsMap[$relation] ?? null;
    }

    /**
     * Retrieve the type of the resource based on the given method definition.
     *
     * @param ReflectionMethod $method
     * @return ?string
     */
    protected function getResourceTypeByMethodDefinition(ReflectionMethod $method) : ?string
    {
        return null;
    }
}
