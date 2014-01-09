<?php


define("ACTIVITY_TYPE_CREATE_CLIENT", 1);
define("ACTIVITY_TYPE_ARCHIVE_CLIENT", 2);
define("ACTIVITY_TYPE_DELETE_CLIENT", 3);
define("ACTIVITY_TYPE_CREATE_INVOICE", 4);
define("ACTIVITY_TYPE_UPDATE_INVOICE", 5);
define("ACTIVITY_TYPE_EMAIL_INVOICE", 6);
define("ACTIVITY_TYPE_VIEW_INVOICE", 7);
define("ACTIVITY_TYPE_ARCHIVE_INVOICE", 8);
define("ACTIVITY_TYPE_DELETE_INVOICE", 9);
define("ACTIVITY_TYPE_CREATE_PAYMENT", 10);
define("ACTIVITY_TYPE_ARCHIVE_PAYMENT", 11);
define("ACTIVITY_TYPE_DELETE_PAYMENT", 12);
define("ACTIVITY_TYPE_CREATE_CREDIT", 13);
define("ACTIVITY_TYPE_ARCHIVE_CREDIT", 14);
define("ACTIVITY_TYPE_DELETE_CREDIT", 15);


class Activity extends Eloquent
{
	public $timestamps = true;
	protected $softDelete = false;	

	public function scopeScope($query)
	{
		return $query->whereAccountId(Auth::user()->account_id);
	}

	public function account()
	{
		return $this->belongsTo('Account');
	}


	private static function getBlank($entity = false)
	{
		$activity = new Activity;

		if (Auth::check()) {
			$activity->user_id = Auth::user()->id;
			$activity->account_id = Auth::user()->account_id;	
		} else if ($entity) {
			$activity->user_id = $entity->user_id;
			$activity->account_id = $entity->account_id;
		} else {
			Utils::fatalError();
		}

		return $activity;
	}

	public static function createClient($client)
	{		
		$activity = Activity::getBlank();
		$activity->client_id = $client->id;
		$activity->activity_type_id = ACTIVITY_TYPE_CREATE_CLIENT;
		$activity->message = Utils::encodeActivity(Auth::user(), 'created', $client);
		$activity->save();		
	}

	public static function updateClient($client)
	{		
		if ($client->is_deleted && !$client->getOriginal('is_deleted'))
		{
			$activity = Activity::getBlank();
			$activity->client_id = $client->id;
			$activity->activity_type_id = ACTIVITY_TYPE_DELETE_CLIENT;
			$activity->message = Utils::encodeActivity(Auth::user(), 'deleted', $client);
			$activity->save();		
		}
	}

	public static function archiveClient($client)
	{
		if (!$client->is_deleted)
		{
			$activity = Activity::getBlank();
			$activity->client_id = $client->id;
			$activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_CLIENT;
			$activity->message = Utils::encodeActivity(Auth::user(), 'archived', $client);
			$activity->balance = $client->balance;
			$activity->save();
		}
	}	

	public static function createInvoice($invoice)
	{
		if ($invoice->is_recurring) 
		{
			$message = Utils::encodeActivity(null, 'created recurring', $invoice);
		} 
		else 
		{
			$message = Utils::encodeActivity(Auth::user(), 'created', $invoice);
		}

		$activity = Activity::getBlank($invoice);
		$activity->invoice_id = $invoice->id;
		$activity->client_id = $invoice->client_id;
		$activity->currency_id = $invoice->currency_id;
		$activity->activity_type_id = ACTIVITY_TYPE_CREATE_INVOICE;
		$activity->message = $message;
		$activity->balance = $invoice->client->balance;
		$activity->save();
	}	

	public static function archiveInvoice($invoice)
	{
		if ($invoice->invoice_status_id < INVOICE_STATUS_SENT)
		{
			return;
		}

		if (!$invoice->is_deleted)
		{
			if ($invoice->balance > 0)
			{
				$client = $invoice->client;
				$client->balance = $client->balance - $invoice->balance;
				$client->save();
			}			

			$activity = Activity::getBlank();
			$activity->invoice_id = $invoice->id;
			$activity->client_id = $invoice->client_id;
			$activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_INVOICE;
			$activity->message = Utils::encodeActivity(Auth::user(), 'archived', $invoice);
			$activity->balance = $invoice->client->balance;
			$activity->adjustment = $invoice->balance;

			$activity->save();
		}
	}

	public static function emailInvoice($invitation)
	{
		$adjustment = 0;
		$client = $invitation->invoice->client;

		if (!$invitation->invoice->isSent())
		{
			$adjustment = $invitation->invoice->amount;
			$client->balance = $client->balance + $adjustment;
			$client->save();
		}

		$userName = Auth::check() ? Auth::user()->getFullName() : '<i>System</i>';
		$activity = Activity::getBlank($invitation);
		$activity->client_id = $invitation->invoice->client_id;
		$activity->invoice_id = $invitation->invoice_id;
		$activity->contact_id = $invitation->contact_id;
		$activity->activity_type_id = ACTIVITY_TYPE_EMAIL_INVOICE;
		$activity->message = Utils::encodeActivity(Auth::check() ? Auth::user() : null, 'emailed', $invitation->invoice, $invitation->contact);
		$activity->balance = $client->balance;
		$activity->adjustment = $adjustment;
		$activity->save();
	}

	public static function updateInvoice($invoice)
	{
		if ($invoice->invoice_status_id < INVOICE_STATUS_SENT)
		{
			return;
		}

		if ($invoice->is_deleted && !$invoice->getOriginal('is_deleted'))
		{
			if ($invoice->balance > 0)
			{
				$client = $invoice->client;
				$client->balance = $client->balance - $invoice->balance;
				$client->save();
			}

			$activity = Activity::getBlank();
			$activity->client_id = $invoice->client_id;
			$activity->invoice_id = $invoice->id;
			$activity->activity_type_id = ACTIVITY_TYPE_DELETE_INVOICE;
			$activity->message = Utils::encodeActivity(Auth::user(), 'deleted', $invoice);
			$activity->balance = $invoice->client->balance;
			$activity->adjustment = $invoice->balance * -1;
			$activity->save();		
		}
		else
		{
			$diff = floatval($invoice->amount) - floatval($invoice->getOriginal('amount'));
			
			if ($diff == 0)
			{
				return;
			}

			$backupInvoice = Invoice::with('invoice_items', 'client.account', 'client.contacts')->find($invoice->id);			

			$client = $invoice->client;
			$client->balance = $client->balance + $diff;
			$client->save();

			$activity = Activity::getBlank($invoice);
			$activity->client_id = $invoice->client_id;
			$activity->invoice_id = $invoice->id;
			$activity->activity_type_id = ACTIVITY_TYPE_UPDATE_INVOICE;
			$activity->message = Utils::encodeActivity(Auth::user(), 'updated', $invoice);
			$activity->balance = $client->balance;
			$activity->adjustment = $diff;
			$activity->json_backup = $backupInvoice->hidePrivateFields()->toJSON();
			$activity->save();
		}
	}

	public static function createPayment($payment)
	{
		$client = $payment->client;
		$client->balance = $client->balance - $payment->amount;
		$client->paid_to_date = $client->paid_to_date + $payment->amount;
		$client->save();

		if (Auth::check())
		{
			$activity = Activity::getBlank();
			$activity->message = Utils::encodeActivity(Auth::user(), 'created payment');
		}
		else
		{
			$activity = new Activity;
			$activity->contact_id = $payment->contact_id;
			$activity->message = Utils::encodeActivity($payment->invitation->contact, 'created payment');			
		}

		$activity->payment_id = $payment->id;

		if ($payment->invoice_id) 
		{
			$activity->invoice_id = $payment->invoice_id;

			$invoice = $payment->invoice;
			$invoice->balance = $invoice->balance - $payment->amount;
			$invoice->save();
		}

		$activity->client_id = $payment->client_id;
		$activity->currency_id = $payment->currency_id;
		$activity->activity_type_id = ACTIVITY_TYPE_CREATE_PAYMENT;
		$activity->balance = $client->balance;
		$activity->adjustment = $payment->amount * -1;
		$activity->save();
	}	

	public static function createCredit($credit)
	{
		$client = $credit->client;
		$client->balance = $client->balance - $credit->amount;
		$client->save();

		$activity = Activity::getBlank();
		$activity->message = Utils::encodeActivity(Auth::user(), 'created credit');
		$activity->credit_id = $credit->id;
		$activity->client_id = $credit->client_id;

		if ($credit->invoice_id) 
		{
			$activity->invoice_id = $credit->invoice_id;

			$invoice = $credit->invoice;
			$invoice->balance = $invoice->amount - $credit->amount;
			$invoice->save();
		}		

		$activity->currency_id = $credit->currency_id;
		$activity->activity_type_id = ACTIVITY_TYPE_CREATE_CREDIT;
		$activity->balance = $client->balance;
		$activity->adjustment = $credit->amount * -1;
		$activity->save();
	}	

	public static function archivePayment($payment)
	{
		$activity = Activity::getBlank();
		$activity->invoice_id = $invoice->id;
		$activity->client_id = $invoice->client_id;
		$activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_PAYMENT;
		$activity->message = Utils::encodeActivity(Auth::user(), 'archived payment');
		$activity->balance = $payment->client->balance;
		$activity->save();
	}	

	public static function viewInvoice($invitation)
	{
		$invoice = $invitation->invoice;
		
		if (!$invoice->isViewed())
		{
			$invoice->invoice_status_id = INVOICE_STATUS_VIEWED;
			$invoice->save();
		}
		
		$now = Carbon::now()->toDateTimeString();

		$invitation->viewed_date = $now;
		$invitation->save();

		$client = $invoice->client;
		$client->last_login = $now;
		$client->save();

		$activity = new Activity;
		$activity->user_id = $invitation->user_id;
		$activity->account_id = $invitation->user->account_id;
		$activity->client_id = $invitation->invoice->client_id;
		$activity->invitation_id = $invitation->id;
		$activity->contact_id = $invitation->contact_id;
		$activity->invoice_id = $invitation->invoice_id;
		$activity->activity_type_id = ACTIVITY_TYPE_VIEW_INVOICE;
		$activity->message = Utils::encodeActivity($invitation->contact, 'viewed', $invitation->invoice);
		$activity->balance = $invitation->invoice->client->balance;
		$activity->save();
	}
}