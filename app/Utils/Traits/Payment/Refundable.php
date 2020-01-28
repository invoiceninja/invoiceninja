<?php

namespace App\Utils\Traits\Payment;

use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Credit;

trait Refundable
{

	public function processRefund(array $data)
	{

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
	}

	private function refundPaymentWithNoInvoicesOrCredits($data)
	{
		//adjust payment refunded column amount
		$this->refunded = $data['refunded'];

		$credit_note = CreditFactory::create($this->company_id, $this->user_id);
		$credit_note->assigned_user_id = isset($this->assigned_user_id) ?: null;
		$credit_note->date = $data['date'];
		$credit_note->number = $this->client->getNextCreditNumber($this->client);
		$credit_note->status_id = Credit::STATUS_DRAFT;

			$credit_line_item = InvoiceItemFactory::create();
			$credit_line_item->quantity = 1;
			$credit_line_item->cost = $data['refunded'];
			$credit_line_item->product_key = ctrans('texts.credit');
			$credit_line_item->notes = ctrans('texts.credit_created_by', ['transaction_reference', $this->number]);
			$credit_line_item->line_total = $data['refunded'];
			$credit_line_item->date = $data['date'];

		$credit_note->line_items[] = $credit_line_item;
		$credit_note->amount = $data['refunded'];
		$credit_note->balance = $data['refunded'];

		$credit_note->save();
		//determine if we need to refund via gateway
		if($data['gateway_refund'] !== false)
		{
			//process gateway refund, on success, reduce the credit note balance to 0
		}
	}



	private function refundPaymentWithInvoices($data)
	{

	}

	private function refundPaymentWithInvoicesAndCredits($data)
	{

	}

	private function createCreditLineItems()
	{

	}

}