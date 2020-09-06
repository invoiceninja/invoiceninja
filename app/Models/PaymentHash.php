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

use Illuminate\Database\Eloquent\Model;

class PaymentHash extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'data' => 'object',
    ];

    public function invoices()
    {
        return $this->data;
    }
}
