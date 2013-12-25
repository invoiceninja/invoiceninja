<?php namespace ninja\mailers;

use Invoice;
use Contact;
use Invitation;
use URL;
use Auth;

class ContactMailer extends Mailer {

	public function sendInvoice(Invoice $invoice)
	{
		$view = 'invoice';
		$data = array('link' => URL::to('view') . '/' . $invoice->invoice_key);		
		$subject = '';

		foreach ($invoice->invitations as $invitation)
		{
			//$invitation->date_sent = 
			$invitation->save();

			$this->sendTo($invitation->contact->email, $subject, $view, $data);
		}

		if (!$invoice->isSent())
		{
			$invoice->invoice_status_id = INVOICE_STATUS_SENT;
			$invoice->save();
		}

		\Event::fire('invoice.sent', $invoice);
	}
}