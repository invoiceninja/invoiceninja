<?php namespace ninja\mailers;

use Invoice;
use Payment;
use Contact;
use Invitation;
use URL;
use Auth;
use Activity;
use Utils;

class ContactMailer extends Mailer {

	public function sendInvoice(Invoice $invoice)
	{
		$view = 'invoice';
		$subject = trans('texts.invoice_subject', ['invoice' => $invoice->invoice_number]);

		$invoice->load('invitations', 'client', 'account');

		foreach ($invoice->invitations as $invitation)
		{
			if (!$invitation->user->email)
			{
				return false;
			}
			
			$invitation->sent_date = \Carbon::now()->toDateTimeString();
			$invitation->save();
	
			$data = [
				'link' => URL::to('view') . '/' . $invitation->invitation_key,
				'clientName' => $invoice->client->getDisplayName(),
				'accountName' => $invoice->account->getDisplayName(),
				'contactName'	=> $invitation->contact->getDisplayName(),
				'invoiceAmount' => Utils::formatMoney($invoice->amount, $invoice->client->currency_id),
				'emailFooter' => $invoice->account->email_footer
			];

			$fromEmail = $invitation->user->email;
			$fromName = $invitation->user->getDisplayName();

			$this->sendTo($invitation->contact->email, $fromEmail, $fromName, $subject, $view, $data);

			Activity::emailInvoice($invitation);
		}
		
		if (!$invoice->isSent())
		{
			$invoice->invoice_status_id = INVOICE_STATUS_SENT;
			$invoice->save();
		}

		\Event::fire('invoice.sent', $invoice);
	}

	public function sendPaymentConfirmation(Payment $payment)
	{
		$view = 'payment_confirmation';
		$subject = trans('texts.payment_subject', ['invoice' => $payment->invoice->invoice_number]);

		$data = [
			'accountName' => $payment->account->getDisplayName(),
			'clientName' => $payment->client->getDisplayName(),
			'emailFooter' => $payment->account->email_footer,
			'paymentAmount' => Utils::formatMoney($payment->amount, $payment->client->currency_id)
		];

		$user = $payment->invitation->user;
		$this->sendTo($payment->contact->email, $user->email, $user->getDisplayName(), $subject, $view, $data);
	}
}