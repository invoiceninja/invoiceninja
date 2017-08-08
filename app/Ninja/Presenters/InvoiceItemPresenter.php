<?php

namespace App\Ninja\Presenters;

use stdClass;

class InvoiceItemPresenter extends EntityPresenter
{
    public function rBits()
    {
        $data = new stdClass();
        $data->description = $this->entity->notes;
        $data->item_price = floatval($this->entity->cost);
        $data->quantity = floatval($this->entity->qty);
        $data->amount = round($data->item_price * $data->quantity, 2);

        return $data;
    }
}
