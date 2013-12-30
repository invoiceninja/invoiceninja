<?php

class Invoice extends EntityModel
{
	protected $hidden = array('id', 'account_id', 'client_id', 'created_at', 'updated_at', 'deleted_at', 'viewed_date');

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

	public function isSent()
	{
		return $this->invoice_status_id >= INVOICE_STATUS_SENT;
	}

	public function shouldSendToday()
	{
		$dayOfWeekToday = date('w');
		$dayOfWeekStart = date('w', strtotime($this->start_date));

		$dayOfMonthToday = date('j');
		$dayOfMonthStart = date('j', strtotime($this->start_date));
			
		if (!$this->last_sent_date) {
			$daysSinceLastSent = 0;
			$monthsSinceLastSent = 0;
		} else {
			$date1 = new DateTime($this->last_sent_date);
			$date2 = new DateTime();
			$diff = $date2->diff($date1);
			$daysSinceLastSent = $diff->format("%a");
			$monthsSinceLastSent = ($diff->format('%y') * 12) + $diff->format('%m');

			if ($daysSinceLastSent == 0) {
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
				return ($dayOfMonthStart == $dayOfMonthToday && (!$daysSinceLastSent || $monthsSinceLastSent == 3)) || $daysSinceLastSent > (3 * 31);
			case FREQUENCY_SIX_MONTHS:
				return ($dayOfMonthStart == $dayOfMonthToday && (!$daysSinceLastSent || $monthsSinceLastSent == 6)) || $daysSinceLastSent > (6 * 31);
			case FREQUENCY_ANNUALLY:
				return ($dayOfMonthStart == $dayOfMonthToday && (!$daysSinceLastSent || $monthsSinceLastSent == 12)) || $daysSinceLastSent > (12 *31);
			default:
				echo "Error: invalid frequency_id - ".$this->frequency_id; exit; //TODO_FIX
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