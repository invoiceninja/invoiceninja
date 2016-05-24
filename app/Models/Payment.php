<?php namespace App\Models;

use Event;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Events\PaymentWasCreated;
use App\Events\PaymentWasRefunded;
use App\Events\PaymentWasVoided;
use App\Events\PaymentCompleted;
use App\Events\PaymentVoided;
use App\Events\PaymentFailed;
use App\Models\PaymentMethod;
use Laracasts\Presenter\PresentableTrait;

class Payment extends EntityModel
{
    use PresentableTrait;
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $presenter = 'App\Ninja\Presenters\PaymentPresenter';

    public function invoice()
    {
        return $this->belongsTo('App\Models\Invoice')->withTrashed();
    }

    public function invitation()
    {
        return $this->belongsTo('App\Models\Invitation');
    }

    public function client()
    {
        return $this->belongsTo('App\Models\Client')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo('App\Models\User')->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function contact()
    {
        return $this->belongsTo('App\Models\Contact');
    }

    public function account_gateway()
    {
        return $this->belongsTo('App\Models\AccountGateway');
    }

    public function payment_type()
    {
        return $this->belongsTo('App\Models\PaymentType');
    }

    public function payment_method()
    {
        return $this->belongsTo('App\Models\PaymentMethod');
    }

    public function payment_status()
    {
        return $this->belongsTo('App\Models\PaymentStatus');
    }

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

    public function getName()
    {
        return trim("payment {$this->transaction_reference}");
    }

    public function isPending()
    {
        return $this->payment_status_id = PAYMENT_STATUS_PENDING;
    }

    public function isFailed()
    {
        return $this->payment_status_id = PAYMENT_STATUS_FAILED;
    }

    public function isCompleted()
    {
        return $this->payment_status_id == PAYMENT_STATUS_COMPLETED;
    }

    public function isPartiallyRefunded()
    {
        return $this->payment_status_id == PAYMENT_STATUS_PARTIALLY_REFUNDED;
    }

    public function isRefunded()
    {
        return $this->payment_status_id == PAYMENT_STATUS_REFUNDED;
    }

    public function isVoided()
    {
        return $this->payment_status_id == PAYMENT_STATUS_VOIDED;
    }

    public function recordRefund($amount = null)
    {
        if (!$this->isRefunded() && !$this->isVoided()) {
            if (!$amount) {
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
        }
    }

    public function markVoided()
    {
        if (!$this->isVoided() && !$this->isPartiallyRefunded() && !$this->isRefunded()) {
            $this->refunded = $this->amount;
            $this->payment_status_id = PAYMENT_STATUS_VOIDED;
            $this->save();

            Event::fire(new PaymentWasVoided($this));
        }
    }

    public function markComplete()
    {
        $this->payment_status_id = PAYMENT_STATUS_COMPLETED;
        $this->save();
        Event::fire(new PaymentCompleted($this));
    }

    public function markFailed($failureMessage)
    {
        $this->payment_status_id = PAYMENT_STATUS_FAILED;
        $this->gateway_error = $failureMessage;
        $this->save();
        Event::fire(new PaymentFailed($this));
    }

    public function getEntityType()
    {
        return ENTITY_PAYMENT;
    }

    public function getCompletedAmount()
    {
        return $this->amount - $this->refunded;
    }

    public function getBankDataAttribute()
    {
        if (!$this->routing_number) {
            return null;
        }
        return PaymentMethod::lookupBankData($this->routing_number);
    }

    public function getBankNameAttribute($bank_name)
    {
        if ($bank_name) {
            return $bank_name;
        }
        $bankData = $this->bank_data;

        return $bankData?$bankData->name:null;
    }

    public function getLast4Attribute($value)
    {
        return $value ? str_pad($value, 4, '0', STR_PAD_LEFT) : null;
    }

    public function getIpAddressAttribute($value)
    {
        return !$value?$value:inet_ntop($value);
    }

    public function setIpAddressAttribute($value)
    {
        $this->attributes['ip_address'] = inet_pton($value);
    }
}

Payment::creating(function ($payment) {

});

Payment::created(function ($payment) {
    event(new PaymentWasCreated($payment));
});
