<?php

class AccountGateway extends EntityModel
{
    public function gateway()
    {
        return $this->belongsTo('Gateway');
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
}
