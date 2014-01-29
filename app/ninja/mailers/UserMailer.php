<?php namespace ninja\mailers;

use Invoice;
use Payment;
use Contact;
use User;
use Utils;

class UserMailer extends Mailer {

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
			'invoiceLink' => "http://www.invoiceninja.com/invoices/{$invoice->public_id}"			
		];

		if ($payment)
		{
			$data['paymentAmount'] = Utils::formatMoney($payment->amount, $invoice->client->currency_id);
		}

		$prep = $type == 'sent' ? 'to' : 'by';
		$subject = "Invoice {$invoice->invoice_number} was $type $prep {$invoice->client->getDisplayName()}";

		$this->sendTo($user->email, CONTACT_EMAIL, $subject, $view, $data);
	}
}