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

use App\Services\Template\TemplateService;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Design
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $company_id
 * @property string $name
 * @property bool $is_custom
 * @property bool $is_active
 * @property object|null $design
 * @property bool $is_deleted
 * @property bool $is_template
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read \App\Models\Company|null $company
 * @property-read string $hashed_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|Design filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Design newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Design newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Design onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Design query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereDesign($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereIsCustom($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Design withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Design withoutTrashed()
 * @mixin \Eloquent
 */
class Design extends BaseModel
{
    use Filterable;
    use SoftDeletes;

    protected $casts = [
        'design' => 'object',
        'deleted_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
    ];

    protected $fillable = [
        'name',
        'design',
        'is_active',
        'is_template',
        'entities',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function service(): TemplateService
    {
        return (new TemplateService($this))->setCompany($this->company);
    }
}
