<?php namespace Ninja\Mailers;

use Invoice;
use Contact;
use Invitation;
use URL;
use Auth;

class ContactMailer extends Mailer {

	public function sendInvoice(Invoice $invoice, Contact $contact)
	{
		$view = 'invoice';
		$data = array('link' => URL::to('view') . '/' . $invoice->invoice_key);		
		$subject = '';

		if (Auth::check()) {
			$invitation = Invitation::createNew();		
		} else {
			$invitation = Invitation::createNew($invoice);		
		}

		$invitation->invoice_id = $invoice->id;
		$invitation->user_id = Auth::check() ? Auth::user()->id : $invoice->user_id;		
		$invitation->contact_id = $contact->id;
		$invitation->invitation_key = str_random(20);				
		$invitation->save();

		return $this->sendTo($contact->email, $subject, $view, $data);
	}
}