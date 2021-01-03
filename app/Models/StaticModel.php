<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaticModel extends Model
{
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
}
