<?php

class InvoiceItem extends EntityModel
{
    public function invoice()
    {
        return $this->belongsTo('Invoice');
    }

    public function product()
    {
        return $this->belongsTo('Product');
    }
}
