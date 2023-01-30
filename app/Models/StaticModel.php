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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;

class StaticModel extends Model
{
    use MakesHash;
    
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
        $query->where('company_id', auth()->user()->companyId());

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
            ->withTrashed()
            ->company()
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }
}
