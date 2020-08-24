<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
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
    const EVENT_MAIL_RETRY_QUEUE = 31; //we use this to queue emails that are spooled and not sent due to the email queue quota being exceeded.

    /*Type IDs*/
    const TYPE_PAYPAL = 300;
    const TYPE_STRIPE = 301;
    const TYPE_LEDGER = 302;
    const TYPE_FAILURE = 303;
    const TYPE_CHECKOUT = 304;
    const TYPE_AUTHORIZE = 305;

    const TYPE_QUOTA_EXCEEDED = 400;
    const TYPE_UPSTREAM_FAILURE = 401;

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

    public function resolveRouteBinding($value)
    {
        if (is_numeric($value)) {
            throw new ModelNotFoundException("Record with value {$value} not found");
        }

        return $this
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }
}
