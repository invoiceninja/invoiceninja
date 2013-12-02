<?php


define("ACTIVITY_TYPE_CREATE_CLIENT", 1);
define("ACTIVITY_TYPE_ARCHIVE_CLIENT", 2);
define("ACTIVITY_TYPE_DELETE_CLIENT", 3);
define("ACTIVITY_TYPE_CREATE_INVOICE", 4);
define("ACTIVITY_TYPE_EMAIL_INVOICE", 5);
define("ACTIVITY_TYPE_VIEW_INVOICE", 6);
define("ACTIVITY_TYPE_ARCHIVE_INVOICE", 7);
define("ACTIVITY_TYPE_DELETE_INVOICE", 8);
define("ACTIVITY_TYPE_CREATE_PAYMENT", 9);
define("ACTIVITY_TYPE_ARCHIVE_PAYMENT", 10);
define("ACTIVITY_TYPE_DELETE_PAYMENT", 11);
define("ACTIVITY_TYPE_CREATE_CREDIT", 12);
define("ACTIVITY_TYPE_ARCHIVE_CREDIT", 13);
define("ACTIVITY_TYPE_DELETE_CREDIT", 14);

class Activity extends Eloquent
{
	private static function getBlank()
	{
		$user = Auth::user();
		$activity = new Activity;
		$activity->user_id = $user->id;
		$activity->account_id = $user->account_id;

		return $activity;
	}

	public static function createClient($client)
	{		
		$activity = Activity::getBlank();
		$activity->client_id = $client->id;
		$activity->activity_type_id = ACTIVITY_TYPE_CREATE_CLIENT;
		$activity->message = Auth::user()->getFullName() . ' created client ' . link_to('clients/'.$client->id, $client->name);

		$activity->save();		
	}

	public static function archiveClient($client)
	{
		$activity = Activity::getBlank();
		$activity->client_id = $client->id;
		$activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_CLIENT;
		$activity->message = Auth::user()->getFullName() . ' archived client ' . $client->name;
		$activity->save();
	}	

	public static function createInvoice($invoice)
	{
		$activity = Activity::getBlank();
		$activity->invoice_id = $invoice->id;
		$activity->client_id = $invoice->client_id;
		$activity->activity_type_id = ACTIVITY_TYPE_CREATE_INVOICE;
		$activity->message = Auth::user()->getFullName() . ' created invoice ' . link_to('invoices/'.$invoice->id, $invoice->invoice_number);
		$activity->save();
	}	

	public static function archiveInvoice($invoice)
	{
		$activity = Activity::getBlank();
		$activity->invoice_id = $invoice->id;
		$activity->client_id = $invoice->client_id;
		$activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_INVOICE;
		$activity->message = Auth::user()->getFullName() . ' archived invoice ' . $invoice->invoice_number;
		$activity->save();
	}

	public static function emailInvoice($invitation)
	{
		$activity = Activity::getBlank();
		$activity->client_id = $invitation->invoice->client_id;
		$activity->invoice_id = $invitation->invoice_id;
		$activity->contact_id = $invitation->contact_id;
		$activity->activity_type_id = ACTIVITY_TYPE_EMAIL_INVOICE;
		//$activity->message = Auth::user()->getFullName() . ' emailed invoice ' . $invitation->invoice->number . ' to ' . $contact->getFullName();
		$activity->save();
	}

	public static function createPayment($payment)
	{
		if (Auth::check())
		{
			$activity = Activity::getBlank();
			$activity->message = Auth::user()->getFullName() . ' created payment ' . $payment->transaction_reference;		
		}
		else
		{
			$activity = new Activity;
			$activity->contact_id = $payment->contact_id;
			//$activity->message = $contact->getFullName() . ' created payment ' . $payment->transaction_reference;		
		}

		$activity->payment_id = $payment->id;
		$activity->invoice_id = $payment->invoice_id;
		$activity->client_id = $payment->invoice->client_id;
		$activity->activity_type_id = ACTIVITY_TYPE_CREATE_PAYMENT;
		$activity->save();
	}	

	public static function archivePayment($payment)
	{
		$activity = Activity::getBlank();
		$activity->invoice_id = $invoice->id;
		$activity->client_id = $invoice->client_id;
		$activity->activity_type_id = ACTIVITY_TYPE_ARCHIVE_PAYMENT;
		$activity->message = Auth::user()->getFullName() . ' archived payment ' . $invoice->number;
		$activity->save();
	}	

	public static function viewInvoice($invitation)
	{
		$activity = new Activity;
		$activity->user_id = $invitation->user_id;
		$activity->account_id = $invitation->user->account_id;
		$activity->client_id = $invitation->invoice->client_id;
		$activity->invitation_id = $invitation->id;
		$activity->contact_id = $invitation->contact_id;
		$activity->invoice_id = $invitation->invoice_id;
		$activity->activity_type_id = ACTIVITY_TYPE_VIEW_INVOICE;
		//$activity->message = $contact->getFullName() . ' viewed invoice ' . $invoice->number;
		$activity->save();
	}
}