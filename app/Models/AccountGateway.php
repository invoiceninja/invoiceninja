<?php namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class AccountGateway extends EntityModel
{
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function gateway()
    {
        return $this->belongsTo('App\Models\Gateway');
    }

    public function getCreditcardTypes()
    {
        $flags = unserialize(CREDIT_CARDS);
        $arrayOfImages = [];

        foreach ($flags as $card => $name) {
            if (($this->accepted_credit_cards & $card) == $card) {
                $arrayOfImages[] = ['source' => asset($name['card']), 'alt' => $name['text']];
            }
        }

        return $arrayOfImages;
    }

    public function isPayPal() {
        return $this->gateway_id == GATEWAY_PAYPAL_EXPRESS;
    }
}

