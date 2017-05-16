<?php

namespace App\Models;

use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Events\PaymentWasCreated;
use App\Events\PaymentWasRefunded;
use App\Events\PaymentWasVoided;
use Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class Payment.
 */
class Payment extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    /**
     * @var array
     */
    protected $fillable = [
        'private_notes',
    ];

    public static $statusClasses = [
        PAYMENT_STATUS_PENDING => 'info',
        PAYMENT_STATUS_COMPLETED => 'success',
        PAYMENT_STATUS_FAILED => 'danger',
        PAYMENT_STATUS_PARTIALLY_REFUNDED => 'primary',
        PAYMENT_STATUS_VOIDED => 'default',
        PAYMENT_STATUS_REFUNDED => 'default',
    ];

    /**
     * @var array
     */
    protected $dates = ['deleted_at'];
    /**
     * @var string
     */
    protected $presenter = 'App\Ninja\Presenters\PaymentPresenter';

    /**
     * @return mixed
     */
    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invitation()
    {
        return $this->belongsTo('App\Models\Invitation');
    }

    /**
     * @return mixed
     */
    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    /**
     * @return mixed
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contact()
    {
        return $this->belongsTo('App\Models\Contact');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account_gateway()
    {
        return $this->belongsTo('App\Models\AccountGateway')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_type()
    {
        return $this->belongsTo('App\Models\PaymentType');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_method()
    {
        return $this->belongsTo('App\Models\PaymentMethod');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function payment_status()
    {
        return $this->belongsTo('App\Models\PaymentStatus');
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return "/payments/{$this->public_id}/edit";
    }

    /*
    public function getAmount()
    {
        return Utils::formatMoney($this->amount, $this->client->getCurrencyId());
    }
    */

    public function scopeExcludeFailed($query)
    {
        $query->whereNotIn('payment_status_id', [PAYMENT_STATUS_VOIDED, PAYMENT_STATUS_FAILED]);

        return $query;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return trim("payment {$this->transaction_reference}");
    }

    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->payment_status_id == PAYMENT_STATUS_PENDING;
    }

    /**
     * @return bool
     */
    public function isFailed()
    {
        return $this->payment_status_id == PAYMENT_STATUS_FAILED;
    }

    /**
     * @return bool
     */
    public function isCompleted()
    {
        return $this->payment_status_id == PAYMENT_STATUS_COMPLETED;
    }

    /**
     * @return bool
     */
    public function isPartiallyRefunded()
    {
        return $this->payment_status_id == PAYMENT_STATUS_PARTIALLY_REFUNDED;
    }

    /**
     * @return bool
     */
    public function isRefunded()
    {
        return $this->payment_status_id == PAYMENT_STATUS_REFUNDED;
    }

    /**
     * @return bool
     */
    public function isVoided()
    {
        return $this->payment_status_id == PAYMENT_STATUS_VOIDED;
    }

    public function isFailedOrVoided()
    {
        return $this->isFailed() || $this->isVoided();
    }

    /**
     * @param null $amount
     *
     * @return bool
     */
    public function recordRefund($amount = null)
    {
        if ($this->isRefunded() || $this->isVoided()) {
            return false;
        }

        if (! $amount) {
            $amount = $this->amount;
        }

        $new_refund = min($this->amount, $this->refunded + $amount);
        $refund_change = $new_refund - $this->refunded;

        if ($refund_change) {
            $this->refunded = $new_refund;
            $this->payment_status_id = $this->refunded == $this->amount ? PAYMENT_STATUS_REFUNDED : PAYMENT_STATUS_PARTIALLY_REFUNDED;
            $this->save();

            Event::fire(new PaymentWasRefunded($this, $refund_change));
        }

        return true;
    }

    /**
     * @return bool
     */
    public function markVoided()
    {
        if ($this->isVoided() || $this->isPartiallyRefunded() || $this->isRefunded()) {
            return false;
        }

        Event::fire(new PaymentWasVoided($this));

        $this->refunded = $this->amount;
        $this->payment_status_id = PAYMENT_STATUS_VOIDED;
        $this->save();

        return true;
    }

    public function markComplete()
    {
        $this->payment_status_id = PAYMENT_STATUS_COMPLETED;
        $this->save();
        Event::fire(new PaymentCompleted($this));
    }

    /**
     * @param string $failureMessage
     */
    public function markFailed($failureMessage = '')
    {
        $this->payment_status_id = PAYMENT_STATUS_FAILED;
        $this->gateway_error = $failureMessage;
        $this->save();
        Event::fire(new PaymentFailed($this));
    }

    /**
     * @return mixed
     */
    public function getEntityType()
    {
        return ENTITY_PAYMENT;
    }

    /**
     * @return mixed
     */
    public function getCompletedAmount()
    {
        return $this->amount - $this->refunded;
    }

    public function canBeRefunded()
    {
        return $this->getCompletedAmount() > 0 && ($this->isCompleted() || $this->isPartiallyRefunded());
    }

    /**
     * @return mixed|null|\stdClass|string
     */
    public function getBankDataAttribute()
    {
        if (! $this->routing_number) {
            return null;
        }

        return PaymentMethod::lookupBankData($this->routing_number);
    }

    /**
     * @param $bank_name
     *
     * @return null
     */
    public function getBankNameAttribute($bank_name)
    {
        if ($bank_name) {
            return $bank_name;
        }
        $bankData = $this->bank_data;

        return $bankData ? $bankData->name : null;
    }

    /**
     * @param $value
     *
     * @return null|string
     */
    public function getLast4Attribute($value)
    {
        return $value ? str_pad($value, 4, '0', STR_PAD_LEFT) : null;
    }

    public static function calcStatusLabel($statusId, $statusName, $amount)
    {
        if ($statusId == PAYMENT_STATUS_PARTIALLY_REFUNDED) {
            return trans('texts.status_partially_refunded_amount', [
                'amount' => $amount,
            ]);
        } else {
            return trans('texts.status_' . strtolower($statusName));
        }
    }

    public static function calcStatusClass($statusId)
    {
        return static::$statusClasses[$statusId];
    }

    public function statusClass()
    {
        return static::calcStatusClass($this->payment_status_id);
    }

    public function statusLabel()
    {
        $amount = $this->account->formatMoney($this->refunded, $this->client);

        return static::calcStatusLabel($this->payment_status_id, $this->payment_status->name, $amount);
    }

    public function invoiceJsonBackup()
    {
        $activity = Activity::wherePaymentId($this->id)
                        ->whereActivityTypeId(ACTIVITY_TYPE_CREATE_PAYMENT)
                        ->get(['json_backup'])
                        ->first();

        return $activity->json_backup;
    }
}

Payment::creating(function ($payment) {
});

Payment::created(function ($payment) {
    event(new PaymentWasCreated($payment));
});
