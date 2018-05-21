<?php

namespace Rexlabs\Laravel\Smokescreen\Console;

use Illuminate\Support\Facades\Schema;

/**
 * The properties listing.
 *
 */
class PropertiesListing
{
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

    /**
     * List the properties of the given Eloquent model.
     *
     * @param string $class
     * @return array
     */
    public function listForEloquent(string $class) : array
    {
        $list = [];
        $table = (new $class)->getTable();
        $columns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            $type = Schema::getColumnType($table, $column);
            $list[$column] = $this->typesMap[$type] ?? null;
        }

        return $list;
    }
}
