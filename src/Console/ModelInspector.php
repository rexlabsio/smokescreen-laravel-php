<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;

/**
 * The includes listing.
 *
 */
class ModelInspector
{
    /**
     * Maps eloquent relationship methods to smokescreen resource types.
     *
     * @var array
     */
    protected $relationsMap = [
        'hasOne'         => 'item',
        'morphOne'       => 'item',
        'belongsTo'      => 'item',
        'morphTo'        => 'item',
        'hasMany'        => 'collection',
        'hasManyThrough' => 'collection',
        'morphMany'      => 'collection',
        'belongsToMany'  => 'collection',
        'morphToMany'    => 'collection',
        'morphedByMany'  => 'collection',
    ];

    /**
     * The map of property types.
     *
     * @var array
     */
    protected $typesMap = [
        'guid' => 'string',
        'boolean' => 'boolean',
        'datetime' => 'datetime',
        'string' => 'string',
        'json' => 'array',
        'integer' => 'integer',
        'date' => 'date',
        'smallint' => 'integer',
        'text' => 'string',
        'decimal' => 'float',
        'bigint' => 'integer',
    ];

    /** @var Model */
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * List the includes of the given Eloquent model.
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getIncludes(): array
    {
        $list = [];
        $methods = (new ReflectionClass($this->getModelClass()))->getMethods(ReflectionMethod::IS_PUBLIC);
        $declaredMethods = array_filter($methods, function (ReflectionMethod $method) {
            return $method->getDeclaringClass()
                    ->getName() === $this->getModelClass();
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
     * List the declared properties of the given Eloquent model.
     *
     * @return array
     */
    public function getDeclaredProperties() : array
    {
        $list = [];
        $class = $this->getModelClass();
        $table = (new $class)->getTable();
        $columns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            $type = Schema::getColumnType($table, $column);
            $list[$column] = $this->typesMap[$type] ?? null;
        }

        return $list;
    }

    /**
     * Get the default properties.
     *
     * @return array
     */
    public function getDefaultProperties(): array
    {
        return [];
    }

    /**
     * Return the model class.
     * @return string
     */
    protected function getModelClass()
    {
        return \get_class($this->model);
    }

    /**
     * Retrieve the type of the resource based on the given method return type.
     *
     * @param ReflectionMethod $method
     *
     * @return string|null
     */
    protected function getResourceTypeByMethodReturnType(ReflectionMethod $method)
    {
        $returnType = (string)$method->getReturnType();
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
     * @todo Implement
     *
     * @param ReflectionMethod $method
     *
     * @return string|null
     */
    protected function getResourceTypeByMethodDefinition(ReflectionMethod $method)
    {
        return null;
    }
}
