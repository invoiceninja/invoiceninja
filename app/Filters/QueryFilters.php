<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Filters;

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Class QueryFilters.
 */
abstract class QueryFilters
{
    use MakesHash;

    /**
     * active status.
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * archived status.
     */
    public const STATUS_ARCHIVED = 'archived';

    /**
     * deleted status.
     */
    public const STATUS_DELETED = 'deleted';

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
     * Request params that are framework/pagination concerns, not filters.
     *
     * These have no filter method by design; without this allow-list they
     * would be reported as unknown filters in the meta.warnings envelope.
     * Params that do have a filter method (filter, status, sort, client_status,
     * client_id, with_trashed, is_deleted, ...) never reach that branch.
     */
    private const RESERVED_PARAMS = [
        'page', 'per_page', 'include', 'index', 'serializer', 'first_load',
        'include_static', 'einvoice', 'clear_cache', 't', '_', 'format',
        'documents', 'search', 'since_updated_at', 'since_id', 'sort', 'stop',
        'rows', 'flat', 'strict',
    ];

    /**
     * Per-request cache of table column listings, keyed by table name.
     *
     * Schema::getColumnListing() is not request-cached on Laravel 12 (each
     * call is an information_schema round-trip). The column set is stable
     * within a request, so memoize it once per table.
     *
     * @var array<string, string[]>
     */
    protected array $column_cache = [];

    /**
     * Unknown filter params encountered during apply().
     *
     * @var string[]
     */
    protected array $filter_warnings = [];

    /**
     * Deprecated filter shapes encountered during apply().
     *
     * @var string[]
     */
    protected array $filter_deprecations = [];

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
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        $this->entityFilter();

        $this->clientFilter();

        foreach ($this->filters() as $name => $value) {
            if (! method_exists($this, $name)) {
                if (! in_array($name, self::RESERVED_PARAMS, true)) {
                    $this->filter_warnings[] = $name;
                }

                continue;
            }

            // potential multi column sort
            if ($name === 'sort' && is_array($value)) {
                foreach ($value as $sort) {
                    if (is_string($sort) && strlen($sort)) {
                        $this->$name($sort);
                    }
                }

                continue;
            }

            if (is_string($value) && strlen($value)) {
                $this->$name($value);
            } else {
                $this->$name();
            }
        }

        $this->surfaceFilterDiagnostics();

        $this->ensureDefaultOrder();

        // nlog('[Search] SQL: ' . $this->builder->toSql() . " Bindings: " . implode(', ', $this->builder->getBindings()));

        return $this->builder->withTrashed();
    }
    
    /**
     * ensureDefaultOrder
     * 
     * Ensures at least a single order by is applied to the query.
     * @return Builder
     */
    protected function ensureDefaultOrder(): Builder
    {
        $query = $this->builder->getQuery();

        if (! empty($query->orders) || ! empty($query->unionOrders)) {
            return $this->builder;
        }

        return $this->builder->orderByDesc(
            $this->builder->getModel()->getQualifiedKeyName()
        );
    }

    /**
     * surfaceFilterDiagnostics
     *
     * Either aborts with a 422 (when the caller opted into strict filtering
     * via ?strict=true / X-Strict-Filters) or, by default, stashes the
     * collected warnings on the request so BaseController::response() can
     * fold them into meta.warnings. The default path is additive and never
     * changes the result set.
     *
     * @return void
     */
    protected function surfaceFilterDiagnostics(): void
    {
        $this->filter_warnings = array_values(array_unique($this->filter_warnings));

        $strict = $this->request->boolean('strict')
            || filter_var($this->request->header('X-Strict-Filters'), FILTER_VALIDATE_BOOLEAN);

        if ($strict && count($this->filter_warnings)) {
            throw ValidationException::withMessages([
                'filters' => ['Unknown filter parameter(s): ' . implode(', ', $this->filter_warnings)],
            ]);
        }

        if (count($this->filter_warnings)) {
            $this->request->attributes->set('filter_warnings', $this->filter_warnings);
        }

        if (count($this->filter_deprecations)) {
            $this->request->attributes->set('filter_deprecations', array_values(array_unique($this->filter_deprecations)));
        }
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
     * Memoized column listing for the builder's table.
     *
     * @return string[]
     */
    protected function tableColumns(): array
    {
        $table = $this->builder->getModel()->getTable();

        return $this->column_cache[$table] ??= Schema::getColumnListing($table);
    }

    /**
     * Explodes the value by delimiter.
     *
     * @param  string $value
     * @return \stdClass
     */
    public function split($value): \stdClass
    {
        $exploded_array = explode(':', $value);

        $parts = new \stdClass();

        $parts->value = $exploded_array[1];
        $parts->operator = $this->operatorConvertor($exploded_array[0]);

        return $parts;
    }

    /**
     * Filters the list based on the status
     * archived, active, deleted.
     *
     * @param string $filter
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
                $query = $query->orWhereNull($this->builder->getModel()->getTable() . '.deleted_at');
            }

            if (in_array(self::STATUS_ARCHIVED, $filters)) {
                $query = $query->orWhere(function ($q) {
                    $q->whereNotNull($this->builder->getModel()->getTable() . '.deleted_at')->where('is_deleted', 0);
                });
            }

            if (in_array(self::STATUS_DELETED, $filters)) {
                $query = $query->orWhere('is_deleted', 1);
            }
        });
    }

    /**
     * String to operator convertor.
     *
     * @param string $operator
     * @return string
     */
    private function operatorConvertor(string $operator): string
    {
        switch ($operator) {
            case 'lt':
                return '<';
            case 'gt':
                return '>';
            case 'lte':
                return '<=';
            case 'gte':
                return '>=';
            case 'eq':
                return '=';
            default:
                return '=';
        }
    }

    /**
     * Filters the query by the contact's client_id.
     *
     * -Can only be used on contact routes
     *
     * @return Builder
     */
    public function clientFilter(): Builder
    {
        if (auth()->guard('contact')->user()) {
            return $this->builder->where('client_id', auth()->guard('contact')->user()->client->id);
        }

        return $this->builder;
    }

    public function created_at($value = '')
    {
        if ($value == '') {
            return $this->builder;
        }

        try {
            if (is_numeric($value)) {
                $created_at = Carbon::createFromTimestamp((int) $value);
            } else {
                $created_at = Carbon::parse($value);
            }

            return $this->builder->where('created_at', '>=', $created_at);
        } catch (\Exception $e) {
            return $this->builder;
        }
    }

    public function updated_at($value = '')
    {
        if (is_null($value) || $value == '') {
            return $this->builder;
        }

        try {
            if (is_numeric($value)) {
                $created_at = Carbon::createFromTimestamp((int) $value);
            } else {
                $created_at = Carbon::parse($value);
            }

            return $this->builder->where('updated_at', '>=', $created_at);
        } catch (\Exception $e) {
            return $this->builder;
        }
    }

    /**
     *
     * @param ?string $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function is_deleted($value = 'true')
    {
        if (is_null($value)) {
            return $this->builder;
        }

        if ($value == 'true') {
            return $this->builder->where('is_deleted', $value)->withTrashed();
        }

        return $this->builder->where('is_deleted', $value);
    }

    public function client_id(string $client_id = ''): Builder
    {
        if (strlen($client_id) == 0 || !in_array('client_id', $this->tableColumns())) {
            return $this->builder;
        }

        return $this->builder->where('client_id', $this->decodePrimaryKey($client_id));
    }

    public function vendor_id(string $vendor_id = ''): Builder
    {
        if (strlen($vendor_id) == 0 || !in_array('vendor_id', $this->tableColumns())) {
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

    /**
     * @return Builder
     */
    public function without_deleted_clients(): Builder
    {
        return $this->builder->where(function ($query) {
            $query->whereHas('client', function ($sub_query) {
                $sub_query->where('is_deleted', 0)->where('deleted_at', null);
            })->orWhere('client_id', null);
        });
    }

    /**
     * @return Builder
     */
    public function without_deleted_vendors(): Builder
    {
        return $this->builder->where(function ($query) {
            $query->whereHas('vendor', function ($sub_query) {
                $sub_query->where('is_deleted', 0)->where('deleted_at', null);
            })->orWhere('vendor_id', null);
        });
    }


    public function with(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        if ($this->with_property == 'id') {

            if (str_contains($value, ',')) {
                $value = $this->transformKeys(explode(',', $value));
            } else {
                $value = [$this->decodePrimaryKey($value)];
            }

        } else {
            $value = [$value];
        }

        return $this->builder
            ->orWhereIn($this->with_property, $value)
            ->orderByRaw("{$this->with_property} = ? DESC", [$value[0]])
            ->company();
    }



    /**
     * Filter by created at date range
     *
     * @param string $date_range
     * @return Builder
     */
    public function created_between(string $date_range = ''): Builder
    {
        $parts = explode(",", $date_range);

        if (count($parts) != 2 || !in_array('created_at', $this->tableColumns())) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($parts[0]);
            $end_date = Carbon::parse($parts[1]);

            return $this->builder->whereBetween('created_at', [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

    }

    /**
     * Filter by updated at date range
     *
     * @param string $date_range
     * @return Builder
     */
    public function updated_between(string $date_range = ''): Builder
    {
        $parts = explode(",", $date_range);

        if (count($parts) != 2 || !in_array('updated_at', $this->tableColumns())) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($parts[0]);
            $end_date = Carbon::parse($parts[1]);

            return $this->builder->whereBetween('updated_at', [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

    }

    /**
     * Filter by date range.
     *
     * Canonical contract: "column,start,end" (column defaults to "date").
     *
     * Legacy shapes are still honoured for one deprecation cycle and recorded
     * on $filter_deprecations (surfaced via meta.warnings.deprecations):
     *  - "start,end"           -> 2-part on the `date` column (the old base /
     *                             RecurringExpenseFilters contract)
     *  - "_,start,end" where _ -> 3-part whose first part is not a real
     *    is not a column          column (the old PaymentFilters contract)
     *
     * @param string $date_range
     * @return Builder
     */
    public function date_range(string $date_range = ''): Builder
    {
        $parts = explode(",", $date_range);

        $columns = $this->tableColumns();

        $deprecation = null;

        if (count($parts) == 2) {
            $column = 'date';
            $start = $parts[0];
            $end = $parts[1];
            $deprecation = 'date_range "start,end" (use "column,start,end")';
        } elseif (count($parts) == 3 && in_array($parts[0], $columns, true)) {
            $column = $parts[0];
            $start = $parts[1];
            $end = $parts[2];
        } elseif (count($parts) == 3) {
            $column = 'date';
            $start = $parts[1];
            $end = $parts[2];
            $deprecation = 'date_range "_,start,end" (use "column,start,end")';
        } else {
            return $this->builder;
        }

        if (!in_array($column, $columns, true)) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($start);
            $end_date = Carbon::parse($end);

            $query = $this->builder->whereBetween($column, [$start_date, $end_date]);

            if ($deprecation) {
                $this->filter_deprecations[] = $deprecation;
            }

            return $query;
        } catch (\Exception $e) {
            return $this->builder;
        }

    }

    public function assigned_user_ids(string $assigned_user_ids = ''): Builder
    {
        if (strlen($assigned_user_ids) == 0 || !in_array('assigned_user_id', $this->tableColumns())) {
            return $this->builder;
        }

        return $this->builder->where(function ($q) use ($assigned_user_ids) {
            $q->whereIn('assigned_user_id', $this->transformKeys(explode(',', $assigned_user_ids)));
        });
    }

    public function client_ids(string $client_ids = ''): Builder
    {
        if (strlen($client_ids) == 0 || !in_array('client_id', $this->tableColumns())) {
            return $this->builder;
        }

        return $this->builder->where(function ($q) use ($client_ids) {
            $q->whereIn('client_id', $this->transformKeys(explode(',', $client_ids)));
        });
    }

    public function custom_value1(string $value = ''): Builder
    {
        if (strlen($value) == 0 || !in_array('custom_value1', $this->tableColumns())) {
            return $this->builder;
        }

        return $this->builder->where('custom_value1', 'like', '%' . $value . '%');
    }

    public function custom_value2(string $value = ''): Builder
    {
        if (strlen($value) == 0 || !in_array('custom_value2', $this->tableColumns())) {
            return $this->builder;
        }

        return $this->builder->where('custom_value2', 'like', '%' . $value . '%');
    }

    public function custom_value3(string $value = ''): Builder
    {
        if (strlen($value) == 0 || !in_array('custom_value3', $this->tableColumns())) {
            return $this->builder;
        }

        return $this->builder->where('custom_value3', 'like', '%' . $value . '%');
    }

    public function custom_value4(string $value = ''): Builder
    {
        if (strlen($value) == 0 || !in_array('custom_value4', $this->tableColumns())) {
            return $this->builder;
        }

        return $this->builder->where('custom_value4', 'like', '%' . $value . '%');
    }

    /**
     * Filter by due date range
     *
     * @param string $date_range
     * @return Builder
     */
    public function due_date_range(string $date_range = ''): Builder
    {

        $parts = explode(",", $date_range);

        if (count($parts) != 2 || !in_array('due_date', $this->tableColumns())) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($parts[0]);
            $end_date = Carbon::parse($parts[1]);

            return $this->builder->whereBetween('due_date', [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

    }



}
