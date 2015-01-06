<?php

class InvoiceTax extends Eloquent
{
	public function invoice()
	{
		return $this->belongsTo('Invoice');
	}
}
