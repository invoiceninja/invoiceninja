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

use App\Models\Traits\Excludable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;

/**
 * App\Models\StaticModel
 *
 * @property-read mixed $id
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel query()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel find($value)
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel with($value)
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel withTrashed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel findOrFail($value)
 * @mixin \Eloquent
 */
class StaticModel extends Model
{
    use MakesHash;
    use Excludable;

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function getIdAttribute()
    {
        return (string) $this->attributes['id'];
    }

    /*
    V2 type of scope
     */
    public function scopeCompany($query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $query->where('company_id', $user->companyId());

        return $query;
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param mixed $value
     * @param null $field
     * @return Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            throw new ModelNotFoundException("Record with value {$value} not found");
        }

        return $this
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }
}
