<?php namespace ninja\mailers;

use Invoice;
use Contact;
use User;

class UserMailer extends Mailer {

	public function sendNotification(User $user, Invoice $invoice, $type)
	{
		if (!$user->email)
		{
			return;
		}

		$view = 'invoice';
		//$data = array('link' => URL::to('view') . '/' . $invoice->invoice_key);		
		$data = [];
		$subject = 'Notification - Invoice ' . $type;

		$this->sendTo($user->email, $subject, $view, $data);
	}
}