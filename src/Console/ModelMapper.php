<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * The includes listing.
 */
class ModelMapper
{
    /**
     * Maps eloquent relationship methods to smokescreen resource types.
     *
     * @var array
     */
    protected $relationsMap = [
        'HasOne'         => 'item',
        'MorphOne'       => 'item',
        'BelongsTo'      => 'item',
        'MorphTo'        => 'item',
        'HasMany'        => 'collection',
        'HasManyThrough' => 'collection',
        'MorphMany'      => 'collection',
        'BelongsToMany'  => 'collection',
        'MorphToMany'    => 'collection',
        'MorphedByMany'  => 'collection',
    ];

    /**
     * Maps schema field types to smokescreen property types.
     *
     * @var array
     */
    protected $schemaTypesMap = [
        'guid'     => 'string',
        'boolean'  => 'boolean',
        'datetime' => 'datetime',
        'string'   => 'string',
        'json'     => 'array',
        'integer'  => 'integer',
        'date'     => 'date',
        'smallint' => 'integer',
        'text'     => 'string',
        'decimal'  => 'float',
        'bigint'   => 'integer',
    ];

    /**
     * @var Model
     */
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    /**
     * List the includes of the given Eloquent model.
     *
     * @throws \ReflectionException
     *
     * @return array
     */
    public function getIncludes(): array
    {
        $includes = [];
        collect((new ReflectionClass($this->getModel()))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(
                function (ReflectionMethod $method) {
                    // We're not interested in inherited methods
                    return $method->getDeclaringClass()->getName() === $this->getModelClass();
                }
            )->each(
                function (ReflectionMethod $method) use (&$includes) {
                    // Only include if we can resolve a resource type
                    if (($type = $this->getResourceType($method)) !== null) {
                        $includes[$method->getName()] = "relation|{$type}";
                    }
                }
            );

        return $includes;
    }

    /**
     * List the declared properties of the given Eloquent model.
     *
     * @return array
     */
    public function getDeclaredProperties(): array
    {
        $props = [];
        $table = $this->getModel()->getTable();
        foreach (Schema::getColumnListing($table) as $column) {
            $type = Schema::getColumnType($table, $column);
            $props[$column] = $this->schemaTypesMap[$type] ?? null;
        }

        return $props;
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
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Return the model class.
     *
     * @return string
     */
    protected function getModelClass()
    {
        return \get_class($this->model);
    }

    /**
     * Get the resource type (item or collection) based on the return signature
     * of the given method.
     *
     * @param ReflectionMethod $method
     *
     * @return string|null
     */
    protected function getResourceType(ReflectionMethod $method)
    {
        // Try to get the type by type-hint.
        if ($type = $this->getResourceTypeByReturnType($method)) {
            return $type;
        }

        // Or, try to get the type by the @return annotation.
        if ($type = $this->getResourceTypeByReturnAnnotation($method)) {
            return $type;
        }

        // Or, try to get the type by the method body.
        if ($type = $this->getResourceTypeByMethodBody($method)) {
            return $type;
        }

        return null;
    }

    /**
     * Retrieve the type of the resource based on the given method return type.
     *
     * @param ReflectionMethod $method
     *
     * @return string|null
     */
    protected function getResourceTypeByReturnType(ReflectionMethod $method)
    {
        $refReturnType = $method->getReturnType();
        if ($refReturnType === null) {
            return null;
        }

        if (!$refReturnType instanceof ReflectionNamedType) {
            return null;
        }

        $returnType = $refReturnType->getName();
        $namespace = 'Illuminate\Database\Eloquent\Relations';

        if (!Str::startsWith($returnType, $namespace)) {
            return null;
        }

        $relation = class_basename($returnType);

        return $this->relationsMap[$relation] ?? null;
    }

    /**
     * Retrieve the type of the resource based on the method's return annotation.
     *
     * @param ReflectionMethod $method
     *
     * @return string|null
     */
    protected function getResourceTypeByReturnAnnotation(ReflectionMethod $method)
    {
        if (preg_match('/@return\s+(\S+)/', $method->getDocComment(), $match)) {
            list($statement, $returnTypes) = $match;

            // Build a regex suitable for matching our relationship keys. EG. hasOne|hasMany...
            $keyPattern = implode(
                '|',
                array_map(
                    function ($key) {
                        return preg_quote($key, '/');
                    },
                    array_keys($this->relationsMap)
                )
            );
            foreach (explode('|', $returnTypes) as $returnType) {
                if (preg_match("/($keyPattern)\$/i", $returnType, $match)) {
                    return $this->relationsMap[$match[1]] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Retrieve the type of the resource based on the method body.
     * This is a pretty crude implementation which simply looks for a method call to one
     * of our relationship keywords.
     *
     * @param ReflectionMethod $method
     *
     * @return string|null
     */
    protected function getResourceTypeByMethodBody(ReflectionMethod $method)
    {
        $startLine = $method->getStartLine();
        $numLines = $method->getEndLine() - $startLine;
        $body = implode('', \array_slice(file($method->getFileName()), $startLine, $numLines));
        if (preg_match('/^\s*return\s+(.+?);/ms', $body, $match)) {
            $returnStmt = $match[1];
            foreach (array_keys($this->relationsMap) as $returnType) {
                // Find "->hasMany(" etc.
                if (preg_match('/->' . preg_quote($returnType, '/') . '\(/i', $returnStmt)) {
                    return $this->relationsMap[$returnType];
                }
            }
        }

        return null;
    }
}
