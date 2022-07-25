<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

class Paymentable extends Pivot
{
    use SoftDeletes;

    protected $table = 'paymentables';

    //protected $dateFormat = 'Y-m-d H:i:s.u';

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'settings' => 'object',
    ];

    public function paymentable()
    {
        return $this->morphTo();
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
