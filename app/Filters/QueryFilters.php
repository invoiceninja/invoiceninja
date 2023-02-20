<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Filters;

//use Illuminate\Database\Query\Builder;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Class QueryFilters.
 */
abstract class QueryFilters
{
    use MakesHash;

    /**
     * active status.
     */
    const STATUS_ACTIVE = 'active';

    /**
     * archived status.
     */
    const STATUS_ARCHIVED = 'archived';

    /**
     * deleted status.
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
     * The "with" filter property column.
     *
     * var string
     */
    protected $with_property = 'id';

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

        $this->clientFilter();

        foreach ($this->filters() as $name => $value) {
            if (! method_exists($this, $name)) {
                continue;
            }

            if (is_string($value) && strlen($value)) {
                $this->$name($value);
            } else {
                $this->$name();
            }
        }

        // nlog('[Search] SQL: ' . $this->builder->toSql() . " Bindings: " . implode(', ', $this->builder->getBindings()));

        return $this->builder->withTrashed();
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
     * Explodes the value by delimiter.
     *
     * @param  string $value
     * @return stdClass
     */
    public function split($value) : \stdClass
    {
        $exploded_array = explode(':', $value);

        $parts = new \stdClass;

        $parts->value = $exploded_array[0];
        $parts->operator = $this->operatorConvertor($exploded_array[1]);

        return $parts;
    }

    /**
     * Filters the list based on the status
     * archived, active, deleted.
     *
     * @param string filter
     * @return Builder
     */
    public function status(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        $filters = explode(',', $filter);

        return $this->builder->where(function ($query) use ($filters) {
            if (in_array(self::STATUS_ACTIVE, $filters)) {
                $query->orWhereNull('deleted_at');
            }

            if (in_array(self::STATUS_ARCHIVED, $filters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('deleted_at')->where('is_deleted', 0);
                });
            }

            if (in_array(self::STATUS_DELETED, $filters)) {
                $query->orWhere('is_deleted', 1);
            }
        });
    }

    /**
     * String to operator convertor.
     *
     * @param string $operator
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

    /**
     * Filters the query by the contact's client_id.
     *
     * -Can only be used on contact routes
     *
     * @return
     */
    public function clientFilter()
    {
        if (auth()->guard('contact')->user()) {
            return $this->builder->where('client_id', auth()->guard('contact')->user()->client->id);
        }
    }

    public function created_at($value = '')
    {
        if ($value == '') {
            return $this->builder;
        }

        try {
            if (is_numeric($value)) {
                $created_at = Carbon::createFromTimestamp((int)$value);
            } else {
                $created_at = Carbon::parse($value);
            }

            return $this->builder->where('created_at', '>=', $created_at);
        } catch(\Exception $e) {
            return $this->builder;
        }
    }

    public function updated_at($value = '')
    {
        if ($value == '') {
            return $this->builder;
        }

        try {
            if (is_numeric($value)) {
                $created_at = Carbon::createFromTimestamp((int)$value);
            } else {
                $created_at = Carbon::parse($value);
            }

            return $this->builder->where('updated_at', '>=', $created_at);
        } catch (\Exception $e) {
            return $this->builder;
        }
    }


    public function is_deleted($value)
    {
        if ($value == 'true') {
            return $this->builder->where('is_deleted', $value)->withTrashed();
        }

        return $this->builder->where('is_deleted', $value);
    }

    public function client_id(string $client_id = ''): Builder
    {
        if (strlen($client_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('client_id', $this->decodePrimaryKey($client_id));
    }

    public function vendor_id(string $vendor_id = ''): Builder
    {
        if (strlen($vendor_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('vendor_id', $this->decodePrimaryKey($vendor_id));
    }

    public function filter_deleted_clients($value)
    {
        if ($value == 'true') {
            return $this->builder->whereHas('client', function (Builder $query) {
                $query->where('is_deleted', 0);
            });
        }

        return $this->builder;
    }

    public function with_trashed($value)
    {
        if ($value == 'false') {
            return $this->builder->where('is_deleted', 0);
        }

        return $this->builder;
    }

    public function with(string $value): Builder
    {
        return $this->builder
            ->orWhere($this->with_property, $value)
            ->orderByRaw("{$this->with_property} = ? DESC", [$value])
            ->company();
    }
}
