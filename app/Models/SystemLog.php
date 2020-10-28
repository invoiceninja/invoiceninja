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

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemLog extends Model
{
    use Filterable;
    use SoftDeletes;
    use MakesHash;

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'log' => 'array',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

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

    public function resolveRouteBinding($value, $field = NULL)
    {
        if (is_numeric($value)) {
            throw new ModelNotFoundException("Record with value {$value} not found");
        }

        return $this
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
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