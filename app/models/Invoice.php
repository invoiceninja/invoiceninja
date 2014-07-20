<?php

class Invoice extends EntityModel
{
	public function account()
	{
		return $this->belongsTo('Account');
	}

	public function user()
	{
		return $this->belongsTo('User');
	}	

	public function client()
	{
		return $this->belongsTo('Client')->withTrashed();
	}

	public function invoice_items()
	{
		return $this->hasMany('InvoiceItem');
	}

	public function invoice_status()
	{
		return $this->belongsTo('InvoiceStatus');
	}

	public function invitations()
	{
		return $this->hasMany('Invitation');
	}

	public function getName()
	{
		return $this->invoice_number;
	}

	public function getLink()
	{
		return link_to('invoices/' . $this->public_id, $this->invoice_number);
	}

	public function getEntityType()
	{
		return $this->is_quote ? ENTITY_QUOTE : ENTITY_INVOICE;
	}	
	
	public function isSent()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_SENT;
	}

	public function isViewed()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_VIEWED;	
	}	

	public function isPaid()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_PAID;	
	}	

	public function hidePrivateFields()
	{
		$this->setVisible([
			'invoice_number', 
			'discount', 
			//'shipping',
			'po_number', 
			'invoice_date', 
			'due_date', 
			'terms', 
			'public_notes', 
			'amount', 
			'balance', 
			'invoice_items', 
			'client', 
			'tax_name', 
			'tax_rate', 
			'account', 
			'invoice_design_id',
			'is_pro',
			'is_quote',
			'custom_value1',
			'custom_value2',
			'custom_taxes1',
			'custom_taxes2']);
		
		$this->client->setVisible([
			'name', 
			'address1', 
			'address2', 
			'city', 
			'state', 
			'postal_code', 
			'work_phone', 
			'payment_terms', 
			'contacts', 
			'country', 
			'currency_id',
			'custom_value1',
			'custom_value2']);

		$this->account->setVisible([
			'name', 
			'address1', 
			'address2', 
			'city', 
			'state', 
			'postal_code', 
			'work_phone', 
			'work_email', 
			'country', 
			'currency_id',
			'custom_label1',
			'custom_value1',
			'custom_label2',
			'custom_value2',
			'custom_client_label1',
			'custom_client_label2',
			'primary_color',
			'secondary_color',
			'hide_quantity',
			'hide_paid_to_date']);		

		foreach ($this->invoice_items as $invoiceItem) 
		{
			$invoiceItem->setVisible([
				'product_key', 
				'notes', 
				'cost', 
				'qty', 
				'tax_name', 
				'tax_rate']);
		}

		foreach ($this->client->contacts as $contact) 
		{
			$contact->setVisible([
				'first_name', 
				'last_name', 
				'email', 
				'phone']);
		}						

		return $this;
	}

	public function shouldSendToday()
	{
		if (!$this->start_date || strtotime($this->start_date) > strtotime('now'))
		{
			return false;
		}

		if ($this->end_date && strtotime($this->end_date) < strtotime('now'))
		{
			return false;
		}

		$dayOfWeekToday = date('w');
		$dayOfWeekStart = date('w', strtotime($this->start_date));

		$dayOfMonthToday = date('j');
		$dayOfMonthStart = date('j', strtotime($this->start_date));
		
		if (!$this->last_sent_date) 
		{
			return true;
		} 
		else 
		{	
			$date1 = new DateTime($this->last_sent_date);
			$date2 = new DateTime();
			$diff = $date2->diff($date1);
			$daysSinceLastSent = $diff->format("%a");
			$monthsSinceLastSent = ($diff->format('%y') * 12) + $diff->format('%m');

			if ($daysSinceLastSent == 0) 
			{
				return false;
			}
		}

		switch ($this->frequency_id)
		{
			case FREQUENCY_WEEKLY:
				return $daysSinceLastSent >= 7;
			case FREQUENCY_TWO_WEEKS:
				return $daysSinceLastSent >= 14;
			case FREQUENCY_FOUR_WEEKS:
				return $daysSinceLastSent >= 28;
			case FREQUENCY_MONTHLY:
				return $monthsSinceLastSent >= 1;
			case FREQUENCY_THREE_MONTHS:
				return $monthsSinceLastSent >= 3;
			case FREQUENCY_SIX_MONTHS:
				return $monthsSinceLastSent >= 6;
			case FREQUENCY_ANNUALLY:
				return $monthsSinceLastSent >= 12;
			default:
				return false;
		}

		return false;
	}	
}

Invoice::created(function($invoice)
{
	Activity::createInvoice($invoice);
});

Invoice::updating(function($invoice)
{
	Activity::updateInvoice($invoice);
});

Invoice::deleting(function($invoice)
{
	Activity::archiveInvoice($invoice);
});