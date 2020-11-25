<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
