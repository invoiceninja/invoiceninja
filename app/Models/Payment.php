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

use App\Events\Payment\PaymentWasRefunded;
use App\Events\Payment\PaymentWasVoided;
use App\Models\GatewayType;
use App\Services\Ledger\LedgerService;
use App\Services\Payment\PaymentService;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Payment\Refundable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Payment extends BaseModel
{
    use MakesHash;
    use Filterable;
    use MakesDates;
    use SoftDeletes;
    use Refundable;
    use Inviteable;

    const STATUS_PENDING = 1;

    const STATUS_CANCELLED = 2;

    const STATUS_FAILED = 3;

    const STATUS_COMPLETED = 4;

    const STATUS_PARTIALLY_REFUNDED = 5;

    const STATUS_REFUNDED = 6;

    const TYPE_CREDIT_CARD = 1;

    const TYPE_BANK_TRANSFER = 2;

    const TYPE_PAYPAL = 3;

    const TYPE_CRYPTO = 4;

    const TYPE_DWOLLA = 5;

    const TYPE_CUSTOM1 = 6;

    const TYPE_ALIPAY = 7;

    const TYPE_SOFORT = 8;

    const TYPE_SEPA = 9;

    const TYPE_GOCARDLESS = 10;

    const TYPE_APPLE_PAY = 11;

    const TYPE_CUSTOM2 = 12;

    const TYPE_CUSTOM3 = 13;

    const TYPE_TOKEN = 'token';

    protected $fillable = [
        'assigned_user_id',
        'client_id',
        'type_id',
        'amount',
        'date',
        'transaction_reference',
        'number',
        'exchange_currency_id',
        'exchange_rate',
        // 'is_manual',
        'private_notes',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_deleted' => 'bool',
        'meta' => 'object',
    ];

    protected $with = [
        'paymentables',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function company_gateway()
    {
        return $this->belongsTo(CompanyGateway::class)->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function contact()
    {
        return $this->belongsTo(ClientContact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function invoices()
    {
        return $this->morphedByMany(Invoice::class, 'paymentable')->withTrashed()->withPivot('amount', 'refunded')->withTimestamps();
    }

    public function credits()
    {
        return $this->morphedByMany(Credit::class, 'paymentable')->withTrashed()->withPivot('amount', 'refunded')->withTimestamps();
    }

    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function type()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function exchange_currency()
    {
        return $this->belongsTo(Currency::class, 'exchange_currency_id', 'id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function translatedType()
    {
        if (! $this->type) {
            return '';
        }

        return ctrans('texts.payment_type_'.$this->type->name);
    }

    public function gateway_type()
    {
        return $this->belongsTo(GatewayType::class);
    }

    public function paymentables()
    {
        return $this->hasMany(Paymentable::class);
    }

    public function formattedAmount()
    {
        return Number::formatMoney($this->amount, $this->client);
    }

    public function clientPaymentDate()
    {
        if (! $this->date) {
            return '';
        }

        $date_format = DateFormat::find($this->client->getSetting('date_format_id'));

        return $this->createClientDate($this->date, $this->client->timezone()->name)->format($date_format->format);
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_PENDING:
                return '<h6><span class="badge badge-secondary">'.ctrans('texts.payment_status_1').'</span></h6>';
                break;
            case self::STATUS_CANCELLED:
                return '<h6><span class="badge badge-warning text-white">'.ctrans('texts.payment_status_2').'</span></h6>';
                break;
            case self::STATUS_FAILED:
                return '<h6><span class="badge badge-danger">'.ctrans('texts.payment_status_3').'</span></h6>';
                break;
            case self::STATUS_COMPLETED:
                return '<h6><span class="badge badge-info">'.ctrans('texts.payment_status_4').'</span></h6>';
                break;
            case self::STATUS_PARTIALLY_REFUNDED:
                return '<h6><span class="badge badge-success">'.ctrans('texts.payment_status_5').'</span></h6>';
                break;
            case self::STATUS_REFUNDED:
                return '<h6><span class="badge badge-primary">'.ctrans('texts.payment_status_6').'</span></h6>';
                break;
            default:
                // code...
                break;
        }
    }

    public static function stringStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_PENDING:
                return ctrans('texts.payment_status_1');
                break;
            case self::STATUS_CANCELLED:
                return ctrans('texts.payment_status_2');
                break;
            case self::STATUS_FAILED:
                return ctrans('texts.payment_status_3');
                break;
            case self::STATUS_COMPLETED:
                return ctrans('texts.payment_status_4');
                break;
            case self::STATUS_PARTIALLY_REFUNDED:
                return ctrans('texts.payment_status_5');
                break;
            case self::STATUS_REFUNDED:
                return ctrans('texts.payment_status_6');
                break;
            default:
                return '';
                break;
        }
    }

    public function ledger()
    {
        return new LedgerService($this);
    }

    public function service()
    {
        return new PaymentService($this);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this
            ->withTrashed()
            ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
    }

    public function refund(array $data) :self
    {
        return $this->service()->refundPayment($data);
    }

    /**
     * @return mixed
     */
    public function getCompletedAmount() :float
    {
        return $this->amount - $this->refunded;
    }

    public function recordRefund($amount = null)
    {
        //do i need $this->isRefunded() here?
        if ($this->isVoided()) {
            return false;
        }

        //if no refund specified
        if (! $amount) {
            $amount = $this->amount;
        }

        $new_refund = min($this->amount, $this->refunded + $amount);
        $refund_change = $new_refund - $this->refunded;

        if ($refund_change) {
            $this->refunded = $new_refund;
            $this->status_id = $this->refunded == $this->amount ? self::STATUS_REFUNDED : self::STATUS_PARTIALLY_REFUNDED;
            $this->save();

            event(new PaymentWasRefunded($this, $refund_change, $this->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }

        return true;
    }

    public function isVoided()
    {
        return $this->status_id == self::STATUS_CANCELLED;
    }

    public function isPartiallyRefunded()
    {
        return $this->status_id == self::STATUS_PARTIALLY_REFUNDED;
    }

    public function isRefunded()
    {
        return $this->status_id == self::STATUS_REFUNDED;
    }

    public function setStatus($status)
    {
        $this->status_id = $status;
        $this->save();
    }

    public function markVoided()
    {
        if ($this->isVoided() || $this->isPartiallyRefunded() || $this->isRefunded()) {
            return false;
        }

        $this->refunded = $this->amount;
        $this->status_id = self::STATUS_CANCELLED;
        $this->save();

        event(new PaymentWasVoided($this, $this->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }

    // public function getLink()
    // {
    //     return route('client.payments.show', $this->hashed_id);
    // }

    public function getLink() :string
    {
        if (Ninja::isHosted()) {
            $domain = isset($this->company->portal_domain) ? $this->company->portal_domain : $this->company->domain();
        } else {
            $domain = config('ninja.app_url');
        }

        return $domain.'/client/payment/'.$this->client->contacts()->first()->contact_key.'/'.$this->hashed_id.'?next=/client/payments/'.$this->hashed_id;
    }

    public function transaction_event()
    {
        $payment = $this->fresh();

        return [
            'payment_id' => $payment->id,
            'payment_amount' => $payment->amount ?: 0,
            'payment_applied' => $payment->applied ?: 0,
            'payment_refunded' => $payment->refunded ?: 0,
            'payment_status' => $payment->status_id ?: 1,
            'paymentables' => $payment->paymentables->toArray(),
            'payment_request' => request() ? request()->all() : [],
        ];
    }

    public function translate_entity()
    {
        return ctrans('texts.payment');
    }
}
