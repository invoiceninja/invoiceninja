<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use App\Events\PaymentWasCreated;
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

    public function getEntityType()
    {
        return ENTITY_PAYMENT;
    }
}

Payment::creating(function ($payment) {
    
});

Payment::created(function ($payment) {
    event(new PaymentWasCreated($payment));
});