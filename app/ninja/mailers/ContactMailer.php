<?php namespace ninja\mailers;

use Invoice;
use Contact;
use Invitation;
use URL;
use Auth;
use Activity;

class ContactMailer extends Mailer {

	public function sendInvoice(Invoice $invoice)
	{
		$view = 'invoice';
		$subject = '';

		foreach ($invoice->invitations as $invitation)
		{
			$invitation->sent_date = \Carbon::now()->toDateTimeString();
			$invitation->save();
	
			$data = array('link' => URL::to('view') . '/' . $invitation->invitation_key);		

			$this->sendTo($invitation->contact->email, $subject, $view, $data);

			Activity::emailInvoice($invitation);
		}
		
		if (!$invoice->isSent())
		{
			$invoice->invoice_status_id = INVOICE_STATUS_SENT;
			$invoice->save();
		}

		\Event::fire('invoice.sent', $invoice);
	}
}