<?php

class Payment extends EntityModel
{
    public function invoice()
    {
        return $this->belongsTo('Invoice')->withTrashed();
    }

    public function invitation()
    {
        return $this->belongsTo('Invitation');
    }

    public function client()
    {
        return $this->belongsTo('Client')->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo('Account');
    }

    public function contact()
    {
        return $this->belongsTo('Contact');
    }

    public function getAmount()
    {
        return Utils::formatMoney($this->amount, $this->client->currency_id);
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
