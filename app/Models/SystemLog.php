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

    const CATEGORY_WEBHOOK = 3;

    const CATEGORY_PDF = 4;

    const CATEGORY_SECURITY = 5;

    /* Event IDs*/
    const EVENT_PAYMENT_RECONCILIATION_FAILURE = 10;

    const EVENT_PAYMENT_RECONCILIATION_SUCCESS = 11;

    const EVENT_GATEWAY_SUCCESS = 21;

    const EVENT_GATEWAY_FAILURE = 22;

    const EVENT_GATEWAY_ERROR = 23;

    const EVENT_MAIL_SEND = 30;

    const EVENT_MAIL_RETRY_QUEUE = 31; //we use this to queue emails that are spooled and not sent due to the email queue quota being exceeded.

    const EVENT_MAIL_BOUNCED = 32;

    const EVENT_MAIL_SPAM_COMPLAINT = 33;

    const EVENT_MAIL_DELIVERY = 34;

    const EVENT_MAIL_OPENED = 35;

    const EVENT_WEBHOOK_RESPONSE = 40;

    const EVENT_WEBHOOK_SUCCESS = 41;

    const EVENT_PDF_RESPONSE = 50;

    const EVENT_AUTHENTICATION_FAILURE = 60;

    const EVENT_USER = 61;

    /*Type IDs*/
    const TYPE_PAYPAL = 300;

    const TYPE_STRIPE = 301;

    const TYPE_LEDGER = 302;

    const TYPE_FAILURE = 303;

    const TYPE_CHECKOUT = 304;

    const TYPE_AUTHORIZE = 305;

    const TYPE_CUSTOM = 306;

    const TYPE_BRAINTREE = 307;

    const TYPE_WEPAY = 309;

    const TYPE_PAYFAST = 310;

    const TYPE_PAYTRACE = 311;

    const TYPE_MOLLIE = 312;

    const TYPE_EWAY = 313;

    const TYPE_SQUARE = 320;

    const TYPE_GOCARDLESS = 321;

    const TYPE_RAZORPAY = 322;

    const TYPE_QUOTA_EXCEEDED = 400;

    const TYPE_UPSTREAM_FAILURE = 401;

    const TYPE_WEBHOOK_RESPONSE = 500;

    const TYPE_PDF_FAILURE = 600;

    const TYPE_PDF_SUCCESS = 601;

    const TYPE_MODIFIED = 701;

    const TYPE_DELETED = 702;

    const TYPE_LOGIN_SUCCESS = 800;

    const TYPE_LOGIN_FAILURE = 801;

    protected $fillable = [
        'client_id',
        'company_id',
        'user_id',
        'log',
        'category_id',
        'event_id',
        'type_id',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Record with value {$value} not found");
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

    public function getCategoryName()
    {
        switch ($this->category_id) {
            case self::CATEGORY_GATEWAY_RESPONSE:
                return 'Gateway';
            case self::CATEGORY_MAIL:
                return 'Mail';
            case self::CATEGORY_WEBHOOK:
                return 'Webhook';
            case self::CATEGORY_PDF:
                return 'PDF';
            case self::CATEGORY_SECURITY:
                return 'Security';

            default:
                return 'undefined';
        }
    }

    public function getEventName()
    {
        switch ($this->event_id) {
            case self::EVENT_PAYMENT_RECONCILIATION_FAILURE:
                return 'Payment reco failure';
            case self::EVENT_PAYMENT_RECONCILIATION_SUCCESS:
                return 'Payment reco success';
            case self::EVENT_GATEWAY_SUCCESS:
                return 'Success';
            case self::EVENT_GATEWAY_FAILURE:
                return 'Failure';
            case self::EVENT_GATEWAY_ERROR:
                return 'Error';
            case self::EVENT_MAIL_SEND:
                return 'Send';
            case self::EVENT_MAIL_RETRY_QUEUE:
                return 'Retry';
            case self::EVENT_MAIL_BOUNCED:
                return 'Bounced';
            case self::EVENT_MAIL_SPAM_COMPLAINT:
                return 'Spam';
            case self::EVENT_MAIL_DELIVERY:
                return 'Delivery';
            case self::EVENT_WEBHOOK_RESPONSE:
                return 'Webhook Response';
            case self::EVENT_PDF_RESPONSE:
                return 'Pdf Response';
            case self::EVENT_AUTHENTICATION_FAILURE:
                return 'Auth Failure';
            case self::EVENT_USER:
                return 'User';
            default:
                return 'undefined';
        }
    }

    public function getTypeName()
    {
        switch ($this->type_id) {
            case self::TYPE_QUOTA_EXCEEDED:
                return 'Quota Exceeded';
            case self::TYPE_UPSTREAM_FAILURE:
                return 'Upstream Failure';
            case self::TYPE_WEBHOOK_RESPONSE:
                return 'Webhook';
            case self::TYPE_PDF_FAILURE:
                return 'Failure';
            case self::TYPE_PDF_SUCCESS:
                return 'Success';
            case self::TYPE_MODIFIED:
                return 'Modified';
            case self::TYPE_DELETED:
                return 'Deleted';
            case self::TYPE_LOGIN_SUCCESS:
                return 'Login Success';
            case self::TYPE_LOGIN_FAILURE:
                return 'Login Failure';
            case self::TYPE_PAYPAL:
                return 'PayPal';
            case self::TYPE_STRIPE:
                return 'Stripe';
            case self::TYPE_LEDGER:
                return 'Ledger';
            case self::TYPE_FAILURE:
                return 'Failure';
            case self::TYPE_CHECKOUT:
                return 'Checkout';
            case self::TYPE_AUTHORIZE:
                return 'Auth.net';
            case self::TYPE_CUSTOM:
                return 'Custom';
            case self::TYPE_BRAINTREE:
                return 'Braintree';
            case self::TYPE_WEPAY:
                return 'WePay';
            case self::TYPE_PAYFAST:
                return 'Payfast';
            default:
                return 'undefined';
        }
    }
}
