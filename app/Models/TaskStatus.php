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

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class TaskStatus.
 *
 * @property int $id
 * @property string|null $name
 * @property int|null $company_id
 * @property int|null $user_id
 * @property int $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int|null $status_sort_order
 * @property string $color
 * @property int|null $status_order
 * @property-read mixed $hashed_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\TaskStatusFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel insert()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereStatusOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereStatusSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TaskStatus withoutTrashed()
 * @mixin \Eloquent
 */
class TaskStatus extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    /**
     * @var bool
     */
    public $timestamps = true;

    /**
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'color',
        'status_order',
    ];

}
