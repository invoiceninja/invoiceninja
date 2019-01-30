<?php

namespace App\Ninja\Presenters;

use Str;
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

    public function tax1()
    {
        $item = $this->entity;

        return $item->tax_name1 . ' ' . $item->tax_rate1 . '%';
    }

    public function tax2()
    {
        $item = $this->entity;

        return $item->tax_name2 . ' ' . $item->tax_rate2 . '%';
    }
}
