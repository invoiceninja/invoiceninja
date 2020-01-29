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

	//public function processRefund(array $data)
	//{

        // if (array_key_exists('invoices', $data) && is_array($data['invoices'])) {

        //     foreach ($data['invoices'] as $adjusted_invoice) {

        //         $invoice = Invoice::whereId($adjusted_invoice['invoice_id'])->first();

        //         $invoice_total_adjustment += $adjusted_invoice['amount'];

        //         if (array_key_exists('credits', $adjusted_invoice)) {

        //             //process and insert credit notes
        //             foreach ($adjusted_invoice['credits'] as $credit) {

        //                 $credit = $this->credit_repo->save($credit, CreditFactory::create(auth()->user()->id, auth()->user()->id), $invoice);

        //             }

        //         } else {
        //             //todo - generate Credit Note for $amount on $invoice - the assumption here is that it is a FULL refund
        //         }

        //     }

        //     if (array_key_exists('amount', $data) && $data['amount'] != $invoice_total_adjustment)
        //         return 'Amount must equal the sum of invoice adjustments';
        // }


        // //adjust applied amount
        // $payment->applied += $invoice_total_adjustment;

        // //adjust clients paid to date
        // $client = $payment->client;
        // $client->paid_to_date += $invoice_total_adjustment;

        // $payment->save();
        // $client->save();
	//}

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
		$this->refunded = $data['refunded'];

		if($data['refunded'] == $this->amount)
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
			$credit_line_item->cost = $data['refunded'];
			$credit_line_item->product_key = ctrans('texts.credit');
			$credit_line_item->notes = ctrans('texts.credit_created_by', ['transaction_reference', $this->number]);
			$credit_line_item->line_total = $data['refunded'];
			$credit_line_item->date = $data['date'];

			$line_items = [];
			$line_items[] = $credit_line_item;

		$credit_note->line_items = $line_items;
		$credit_note->amount = $data['refunded'];
		$credit_note->balance = $data['refunded'];

		$credit_note->save();

		$this->createActivity($data, $credit_note->id);

		//determine if we need to refund via gateway
		if($data['gateway_refund'] !== false)
		{
			//process gateway refund, on success, reduce the credit note balance to 0
		}


		$this->save();

		$this->client->paid_to_date -= $data['refunded'];
		$this->client->save();

		return $this;
	}



	private function refundPaymentWithInvoices($data)
	{
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