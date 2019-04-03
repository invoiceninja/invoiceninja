<?php

namespace App\Filters;

use App\Models\User;
//use Illuminate\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\Request;

/**
 * Class QueryFilters
 * @package App\Filters
 */
abstract class QueryFilters
{
    /**
     * active status
     */
    const STATUS_ACTIVE = 'active';

    /**
     * archived status
     */
    const STATUS_ARCHIVED = 'archived';

    /**
     * deleted status
     */
    const STATUS_DELETED = 'deleted';

    /**
     * The request object.
     *
     * @var Request
     */
    protected $request;

    /**
     * The builder instance.
     *
     * @var Builder
     */
    protected $builder;

    /**
     * Create a new QueryFilters instance.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {

        $this->request = $request;

    }

    /**
     * Apply the filters to the builder.
     *
     * @param  Builder $builder
     * @return Builder
     */
    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        $this->entityFilter();

        foreach ($this->filters() as $name => $value) {
            if (! method_exists($this, $name)) {
                continue;
            }

            if (strlen($value)) {
                $this->$name($value);
            } else {
                $this->$name();
            }
        }

        return $this->builder;
    }

    /**
     * Get all request filters data.
     *
     * @return array
     */
    public function filters()
    {

        return $this->request->all();

    }

    /**
     * Explodes the value by delimiter
     * 
     * @param  string $value
     * @return stdClass 
     */
    public function split($value) : stdClass
    {

        $exploded_array = explode(":", $value);

        $parts = new stdClass;

        $parts->value = $exploded_array[0];
        $parts->operator = $this->operatorConvertor($exploded_array[1]);

        return $parts;

    }

    /**
     * String to operator convertor
     * 
     * @param  string $value
     * @return string
     */
    private function operatorConvertor(string $operator) : string
    {

        switch ($operator) {
            case 'lt':
                return '<';
                break;
            case 'gt':
                return '>';
                break;
            case 'lte':
                return '<=';
                break;
            case 'gte':
                return '>=';
                break;
            case 'eq':
                return '=';
                break;
            default:
                return '=';
                break;
                
        }
    }
}