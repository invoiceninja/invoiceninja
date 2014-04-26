<?php namespace ninja\mailers;

use Invoice;
use Payment;
use Contact;
use User;
use Utils;

class UserMailer extends Mailer {

	public function sendConfirmation(User $user)
	{
		if (!$user->email)
		{
			return;
		}

		$view = 'confirm';
		$subject = trans('texts.confirmation_subject');

		$data = [
			'user' => $user
		];
		
		$this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);		
	}

	public function sendNotification(User $user, Invoice $invoice, $type, Payment $payment = null)
	{
		if (!$user->email)
		{
			return;
		}

		$view = 'invoice_' . $type;

		$data = [
			'clientName' => $invoice->client->getDisplayName(),
			'accountName' => $invoice->account->getDisplayName(),
			'userName' => $user->getDisplayName(),
			'invoiceAmount' => Utils::formatMoney($invoice->amount, $invoice->client->currency_id),
			'invoiceNumber' => $invoice->invoice_number,
			'invoiceLink' => "http://".$_SERVER['SERVER_NAME']."/invoices/{$invoice->public_id}"			
		];

		if ($payment)
		{
			$data['paymentAmount'] = Utils::formatMoney($payment->amount, $invoice->client->currency_id);
		}

		$subject = trans('texts.notification_'.$type.'_subject', ['invoice'=>$invoice->invoice_number, 'client'=>$invoice->client->getDisplayName()]);

		$this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
	}
}