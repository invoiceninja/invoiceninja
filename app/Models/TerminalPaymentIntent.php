<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\TerminalPaymentIntent
 *
 * One in person (Stripe Terminal) collection and the PaymentIntent we created for it.
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $invoice_id
 * @property int $company_gateway_id
 * @property int|null $payment_hash_id
 * @property int|null $payment_id
 * @property string $collection_id
 * @property string|null $payment_intent_id
 * @property string $currency
 * @property bool $livemode
 * @property float $amount
 * @property float $fee_gross
 * @property float $fee_net
 * @property bool $apply_fee
 * @property string|null $surcharge_path
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $claimed_at
 * @property \Illuminate\Support\Carbon|null $touched_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\CompanyGateway|null $company_gateway
 * @property-read \App\Models\PaymentHash|null $payment_hash
 * @mixin \Eloquent
 */
class TerminalPaymentIntent extends Model
{
    public const STATUS_CREATING = 'creating';

    public const STATUS_OPEN = 'open';

    public const STATUS_RECORDING = 'recording';

    public const STATUS_RECORDED = 'recorded';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_ORPHANED = 'orphaned';

    public const STATUS_REJECTED = 'rejected';

    /* Statuses that hold the invoice: no second collection while one exists */
    public const OPEN_STATUSES = [self::STATUS_CREATING, self::STATUS_OPEN, self::STATUS_RECORDING];

    public const SURCHARGE_PATH_STRIPE = 'stripe';

    public const SURCHARGE_PATH_RECOLLECT = 'recollect';

    public const SURCHARGE_PATH_NONE = 'none';

    protected $guarded = ['id'];

    protected $casts = [
        'livemode' => 'bool',
        'apply_fee' => 'bool',
        'amount' => 'float',
        'fee_gross' => 'float',
        'fee_net' => 'float',
        'claimed_at' => 'datetime',
        'touched_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function company_gateway()
    {
        return $this->belongsTo(CompanyGateway::class)->withTrashed();
    }

    public function payment_hash()
    {
        return $this->belongsTo(PaymentHash::class);
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_RECORDED, self::STATUS_CANCELED, self::STATUS_FAILED, self::STATUS_ORPHANED, self::STATUS_REJECTED]);
    }

    /* Untouched for 20 minutes: abandoned, unless Stripe says it was charged */
    public function isStale(): bool
    {
        return ($this->touched_at ?? $this->created_at)->lt(now()->subMinutes(20));
    }
}
