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

    /**
     * Parses a comparable-date wire value into its operator + Carbon.
     *
     * Canonical wire is the PREFIX form `op:value`
     * (`gte:2026-01-01`, `lt:2026-01-01`) where op is one of
     * lt/gt/lte/gte/eq (mapped by operatorConvertor()). A bare value
     * with no operator prefix falls back to $defaultOperator — for the
     * date filters that is `>=`, preserving the historical
     * `created_at=<date>` "on or after" behaviour.
     *
     * Returns [operator, Carbon $date, bool $dateOnly], or null when the
     * value is empty or unparseable — callers translate null into the
     * unfiltered builder to match the framework's silent-skip contract.
     *
     * @return array{0:string,1:\Carbon\Carbon,2:bool}|null
     */
    private function parseComparableDate($value, string $defaultOperator): ?array
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        $operator = $defaultOperator;
        $raw = (string) $value;

        $parts = explode(':', $raw, 2);
        if (count($parts) === 2 && in_array($parts[0], ['lt', 'gt', 'lte', 'gte', 'eq'], true)) {
            $operator = $this->operatorConvertor($parts[0]);
            $raw = $parts[1];
        }

        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        try {
            $date_only = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw);

            if (is_numeric($raw)) {
                $date = Carbon::createFromTimestamp((int) $raw);
                $date_only = false;
            } else {
                $date = Carbon::parse($raw);
            }
        } catch (\Exception $e) {
            return null;
        }

        return [$operator, $date, $date_only];
    }

    /**
     * Applies a comparable date filter on a true DATE column ($column).
     *
     * DATE columns compare correctly at day granularity with a plain,
     * indexed where() — DATE(col) is equivalent to col here — so we never
     * reach for whereDate() (which would wrap the column in a function and
     * drop the index). Datetime columns must use comparableDatetime().
     */
    protected function comparableDate(string $column, $value, string $defaultOperator = '>='): Builder
    {
        $parsed = $this->parseComparableDate($value, $defaultOperator);

        if ($parsed === null) {
            return $this->builder;
        }

        [$operator, $date, $date_only] = $parsed;

        return $this->builder->where($column, $operator, $date_only ? $date->toDateString() : $date);
    }

    /**
     * Applies a comparable date filter on a DATETIME column ($column),
     * e.g. created_at / updated_at / due_date.
     *
     * A bare date (`YYYY-MM-DD`) must compare per calendar day, not per
     * microsecond. whereDate() would do that but wraps the column in
     * DATE() and defeats the index. Instead we translate the operator
     * into a half-open range on the bare column — provably identical to
     * whereDate() for every operator while keeping the predicate sargable.
     * A full timestamp (or numeric epoch) compares exactly, as before.
     */
    protected function comparableDatetime(string $column, $value, string $defaultOperator = '>='): Builder
    {
        $parsed = $this->parseComparableDate($value, $defaultOperator);

        if ($parsed === null) {
            return $this->builder;
        }

        [$operator, $date, $date_only] = $parsed;

        if (! $date_only) {
            return $this->builder->where($column, $operator, $date);
        }

        $start = $date->copy()->startOfDay();
        $next = $start->copy()->addDay();

        switch ($operator) {
            case '>':
                return $this->builder->where($column, '>=', $next);
            case '>=':
                return $this->builder->where($column, '>=', $start);
            case '<':
                return $this->builder->where($column, '<', $start);
            case '<=':
                return $this->builder->where($column, '<', $next);
            case '=':
            default:
                return $this->builder
                    ->where($column, '>=', $start)
                    ->where($column, '<', $next);
        }
    }

    public function created_at($value = '')
    {
        return $this->comparableDatetime('created_at', $value, '>=');
    }

    public function updated_at($value = '')
    {
        return $this->comparableDatetime('updated_at', $value, '>=');
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
     * Legacy shapes are still honoured for backward compatibility:
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

        if (count($parts) == 2) {
            $column = 'date';
            $start = $parts[0];
            $end = $parts[1];
        } elseif (count($parts) == 3 && in_array($parts[0], $columns, true)) {
            $column = $parts[0];
            $start = $parts[1];
            $end = $parts[2];
        } elseif (count($parts) == 3) {
            $column = 'date';
            $start = $parts[1];
            $end = $parts[2];
        } else {
            return $this->builder;
        }

        if (!in_array($column, $columns, true)) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($start);
            $end_date = Carbon::parse($end);

            return $this->builder->whereBetween($column, [$start_date, $end_date]);
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

    public function tag_ids(string $tag_ids = ''): Builder
    {
        if (strlen($tag_ids) == 0 || !method_exists($this->builder->getModel(), 'tags')) {
            return $this->builder;
        }

        $ids = $this->transformKeys(explode(',', $tag_ids));

        return $this->builder->whereHas('tags', function (Builder $query) use ($ids) {
            $query->whereIn('tags.id', $ids);
        });
    }
    /**
     * Filter by due date range.
     *
     * Mirrors {@see date_range()} (arity-tolerant, column-aware) but
     * defaults the column to `due_date` instead of `date`. Accepts:
     *  - "start,end"            -> 2-part legacy, column = due_date
     *  - "due_date,start,end"   -> 3-part canonical (column is a real
     *                              table column)
     *  - "_,start,end"          -> 3-part whose first part is not a real
     *                              column -> defaults to due_date
     *
     * @param string $date_range
     * @return Builder
     */
    public function due_date_range(string $date_range = ''): Builder
    {
        $parts = explode(",", $date_range);

        $columns = $this->tableColumns();

        if (count($parts) == 2) {
            $column = 'due_date';
            $start = $parts[0];
            $end = $parts[1];
        } elseif (count($parts) == 3 && in_array($parts[0], $columns, true)) {
            $column = $parts[0];
            $start = $parts[1];
            $end = $parts[2];
        } elseif (count($parts) == 3) {
            $column = 'due_date';
            $start = $parts[1];
            $end = $parts[2];
        } else {
            return $this->builder;
        }

        if (!in_array($column, $columns, true)) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($start);
            $end_date = Carbon::parse($end);

            return $this->builder->whereBetween($column, [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

    }



}
