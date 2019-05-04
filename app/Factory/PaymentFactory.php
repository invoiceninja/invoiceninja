<?php

namespace App\Factory;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;

class PaymentFactory
{
	public static function create(int $company_id, int $user_id) :Payment
	{
		$payment = new Payment();
		
		$payment->company_id = $company_id;
		$payment->user_id = $user_id;
		$payment->client_id = 0;
		$payment->client_contact_id = null;
		$payment->invitation_id = null;
		$payment->account_gateway_id = null;
		$payment->payment_type_id = null;
		$payment->is_deleted = false;
		$payment->amount = 0;
		$payment->payment_date = null;
		$payment->transaction_reference = null;
		$payment->payer_id = null;
		$payment->invoice_id = 0;
		
		return $payment;
	}
}



