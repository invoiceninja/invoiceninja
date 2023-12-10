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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\TaxRate
 *
 * @property int $id
 * @property int $company_id
 * @property int|null $user_id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string $name
 * @property float $rate
 * @property bool $is_deleted
 * @property-read mixed $hashed_id
 * @property-read mixed $tax_rate_id
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\TaxRateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate withoutTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|TaxRate firstOrNew()
 * @mixin \Eloquent
 */
class TaxRate extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'name',
        'rate',
    ];

    // protected $appends = ['tax_rate_id'];

    public function getEntityType()
    {
        return self::class;
    }

    public function getRouteKeyName()
    {
        return 'tax_rate_id';
    }

    public function getTaxRateIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }
}
