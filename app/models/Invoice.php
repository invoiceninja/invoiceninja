<?php

class Invoice extends EntityModel
{
	public function account()
	{
		return $this->belongsTo('Account');
	}

	public function client()
	{
		return $this->belongsTo('Client');
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

	public function getEntityType()
	{
		return ENTITY_INVOICE;
	}	

	public function getInvoiceDateAttribute($value)
	{
		return Utils::fromSqlDate($value);
	}
	
	public function getDueDateAttribute($value)
	{
		return Utils::fromSqlDate($value);
	}
	
	public function getStartDateAttribute($value)
	{
		return Utils::fromSqlDate($value);
	}
	
	public function getEndDateAttribute($value)
	{
		return Utils::fromSqlDate($value);
	}

	public function isSent()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_SENT;
	}

	public function isViewed()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_VIEWED;	
	}	

	public function hidePrivateFields()
	{
		$this->setVisible(['invoice_number', 'discount', 'po_number', 'invoice_date', 'due_date', 'terms', 'currency_id', 'public_notes', 'amount', 'balance', 'invoice_items', 'client', 'tax_name', 'tax_rate', 'account']);
		
		$this->client->setVisible(['name', 'address1', 'address2', 'city', 'state', 'postal_code', 'work_phone', 'payment_terms', 'contacts', 'country']);
		$this->account->setVisible(['name', 'address1', 'address2', 'city', 'state', 'postal_code', 'country']);		

		foreach ($this->invoice_items as $invoiceItem) 
		{
			$invoiceItem->setVisible(['product_key', 'notes', 'cost', 'qty', 'tax_name', 'tax_rate']);
		}

		foreach ($this->client->contacts as $contact) 
		{
			$contact->setVisible(['first_name', 'last_name', 'email', 'phone']);
		}						

		return $this;
	}

	public function shouldSendToday()
	{
		//$dayOfWeekStart = strtotime($this->start_date);
		return false;

		$dayOfWeekToday = date('w');
		$dayOfWeekStart = date('w', strtotime($this->start_date));

		$dayOfMonthToday = date('j');
		$dayOfMonthStart = date('j', strtotime($this->start_date));
		
		if (!$this->last_sent_date) 
		{
			$daysSinceLastSent = 0;
			$monthsSinceLastSent = 0;
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
				return $dayOfWeekStart == $dayOfWeekToday;
			case FREQUENCY_TWO_WEEKS:
				return $dayOfWeekStart == $dayOfWeekToday && (!$daysSinceLastSent || $daysSinceLastSent == 14);
			case FREQUENCY_FOUR_WEEKS:
				return $dayOfWeekStart == $dayOfWeekToday && (!$daysSinceLastSent || $daysSinceLastSent == 28);
			case FREQUENCY_MONTHLY:
				return $dayOfMonthStart == $dayOfMonthToday || $daysSinceLastSent > 31;
			case FREQUENCY_THREE_MONTHS:
				return ($dayOfMonthStart == $dayOfMonthToday && (!$daysSinceLastSent || $monthsSinceLastSent == 3)) || $daysSinceLastSent > 92;
			case FREQUENCY_SIX_MONTHS:
				return ($dayOfMonthStart == $dayOfMonthToday && (!$daysSinceLastSent || $monthsSinceLastSent == 6)) || $daysSinceLastSent > 183;
			case FREQUENCY_ANNUALLY:
				return ($dayOfMonthStart == $dayOfMonthToday && (!$daysSinceLastSent || $monthsSinceLastSent == 12)) || $daysSinceLastSent > 365;
			default:
				Utils::fatalError("Invalid frequency supplied: " . $this->frequency_id);
				break;
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