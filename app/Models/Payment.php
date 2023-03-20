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

use App\Events\Payment\PaymentWasRefunded;
use App\Events\Payment\PaymentWasVoided;
use App\Services\Ledger\LedgerService;
use App\Services\Payment\PaymentService;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Payment\Refundable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Payment
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int|null $user_id
 * @property int|null $assigned_user_id
 * @property int|null $client_contact_id
 * @property int|null $invitation_id
 * @property int|null $company_gateway_id
 * @property int|null $gateway_type_id
 * @property int|null $type_id
 * @property int $status_id
 * @property string $amount
 * @property string $refunded
 * @property string $applied
 * @property string|null $date
 * @property string|null $transaction_reference
 * @property string|null $payer_id
 * @property string|null $number
 * @property string|null $private_notes
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property bool $is_deleted
 * @property int $is_manual
 * @property float $exchange_rate
 * @property int $currency_id
 * @property int|null $exchange_currency_id
 * @property object|null $meta
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $transaction_id
 * @property string|null $idempotency_key
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\CompanyGateway|null $company_gateway
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read int|null $company_ledger_count
 * @property-read \App\Models\ClientContact|null $contact
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read int|null $credits_count
 * @property-read \App\Models\Currency|null $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \App\Models\Currency|null $exchange_currency
 * @property-read \App\Models\GatewayType|null $gateway_type
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Paymentable> $paymentables
 * @property-read int|null $paymentables_count
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\PaymentType|null $type
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\PaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Payment filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereApplied($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereClientContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCompanyGatewayId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereExchangeCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereGatewayTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereInvitationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereIsManual($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment wherePayerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment wherePrivateNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereRefunded($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Paymentable> $paymentables
 * @mixin \Eloquent
 */
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
        if (! $this->type_id) {
            return '';
        }

        $pt = new PaymentType();

        return $pt->name($this->type_id);
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

    public function formatAmount(float $amount): string
    {
        return Number::formatMoney($amount, $this->client);
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
