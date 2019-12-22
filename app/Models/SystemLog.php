<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    /* Category IDs */
    const CATEGORY_GATEWAY_RESPONSE = 1;
    const CATEGORY_MAIL = 2;

    /* Event IDs*/
    const EVENT_PAYMENT_RECONCILIATION_FAILURE = 10;
    const EVENT_PAYMENT_RECONCILIATION_SUCCESS = 11;
    
    const EVENT_GATEWAY_SUCCESS = 21;
    const EVENT_GATEWAY_FAILURE = 22;
    const EVENT_GATEWAY_ERROR = 23;

    const EVENT_MAIL_SEND = 30;

    /*Type IDs*/
    const TYPE_PAYPAL = 300;
    const TYPE_STRIPE = 301;
    const TYPE_LEDGER = 302;
    const TYPE_FAILURE = 303;

    protected $fillable = [
        'client_id',
        'company_id',
        'user_id',
        'log',
        'category_id',
        'event_id',
        'type_id',
    ];

    protected $casts = [
        'log' => 'array'
    ];
}
