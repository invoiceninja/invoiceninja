<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

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

    public function account()
    {
        return $this->belongsTo('App\Models\Account');
    }

    public function contact()
    {
        return $this->belongsTo('App\Models\Contact');
    }

    public function getAmount()
    {
        return Utils::formatMoney($this->amount, $this->client->getCurrencyId());
    }

    public function getName()
    {
        return trim("payment {$this->transaction_reference}");
    }

    public function getEntityType()
    {
        return ENTITY_PAYMENT;
    }
}

Payment::created(function ($payment) {
    Activity::createPayment($payment);
});

Payment::updating(function ($payment) {
    Activity::updatePayment($payment);
});

Payment::deleting(function ($payment) {
    Activity::archivePayment($payment);
});

Payment::restoring(function ($payment) {
    Activity::restorePayment($payment);
});
