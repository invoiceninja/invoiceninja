<?php

namespace App\Utils\Traits\Payment;

use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Activity;
use App\Models\Credit;
use App\Models\Payment;
use App\Repositories\ActivityRepository;

trait Refundable
{

	public function processRefund(array $data)
	{

		if(isset($data['invoices']) && isset($data['credits']))
			return $this->refundPaymentWithInvoicesAndCredits($data);
		else if(isset($data['invoices']))
			return $this->refundPaymentWithInvoices($data);

		return $this->refundPaymentWithNoInvoicesOrCredits($data);
	}

	private function refundPaymentWithNoInvoicesOrCredits(array $data)
	{
		//adjust payment refunded column amount
		$this->refunded = $data['amount'];

		if($data['amount'] == $this->amount)
			$this->status_id = Payment::STATUS_REFUNDED;
		else
			$this->status_id = Payment::STATUS_PARTIALLY_REFUNDED; 

		$credit_note = CreditFactory::create($this->company_id, $this->user_id);
		$credit_note->assigned_user_id = isset($this->assigned_user_id) ?: null;
		$credit_note->date = $data['date'];
		$credit_note->number = $this->client->getNextCreditNumber($this->client);
		$credit_note->status_id = Credit::STATUS_DRAFT;
		$credit_note->client_id = $this->client->id;

			$credit_line_item = InvoiceItemFactory::create();
			$credit_line_item->quantity = 1;
			$credit_line_item->cost = $data['amount'];
			$credit_line_item->product_key = ctrans('texts.credit');
			$credit_line_item->notes = ctrans('texts.credit_created_by', ['transaction_reference', $this->number]);
			$credit_line_item->line_total = $data['amount'];
			$credit_line_item->date = $data['date'];

			$line_items = [];
			$line_items[] = $credit_line_item;

		$credit_note->line_items = $line_items;
		$credit_note->amount = $data['amount'];
		$credit_note->balance = $data['amount'];

		$credit_note->save();

		$this->createActivity($data, $credit_note->id);

		//determine if we need to refund via gateway
		if($data['gateway_refund'] !== false)
		{
			//todo process gateway refund, on success, reduce the credit note balance to 0
		}


		$this->save();

		$this->client->paid_to_date -= $data['amount'];
		$this->client->save();

		return $this;
	}



	private function refundPaymentWithInvoices($data)
	{

		$total_refund = 0;

		foreach($data['invoices'] as $invoice)
		{
			$total_refund += $invoice['amount'];
		}

		$data['amount'] = $total_refund;

		if($total_refund == $this->amount)
			$this->status_id = Payment::STATUS_REFUNDED;
		else
			$this->status_id = Payment::STATUS_PARTIALLY_REFUNDED; 

		return $this;
	}

	private function refundPaymentWithInvoicesAndCredits($data)
	{
		return $this;
	}

	private function createCreditLineItems()
	{

	}

	private function createActivity(array $data, int $credit_id)
	{

        $fields = new \stdClass;
        $activity_repo = new ActivityRepository();

        $fields->payment_id = $this->id;
        $fields->user_id = $this->user_id;
        $fields->company_id = $this->company_id;
        $fields->activity_type_id = Activity::REFUNDED_PAYMENT;
        $fields->credit_id = $credit_id;

        if(isset($data['invoices']))
        {
	        foreach ($data['invoices'] as $invoice) 
	        { 
	            $fields->invoice_id = $invoice->id;	
				
				$activity_repo->save($fields, $this);
	    
	        }
        }
        else
        	$activity_repo->save($fields, $this);
	    
	}

}