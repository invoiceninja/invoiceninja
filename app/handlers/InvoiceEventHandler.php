<?php

use ninja\mailers\UserMailer as Mailer;

class InvoiceEventHandler
{
	protected $mailer;

	public function __construct(Mailer $mailer)
	{
		$this->mailer = $mailer;
	}	

	public function subscribe($events)
	{
		$events->listen('invoice.sent', 'InvoiceEventHandler@onSent');
		$events->listen('invoice.viewed', 'InvoiceEventHandler@onViewed');
		$events->listen('invoice.paid', 'InvoiceEventHandler@onPaid');
	}

	public function onSent($invoice)
	{
		$this->sendNotifications($invoice, 'sent');
	}

	public function onViewed($invoice)
	{
		$this->sendNotifications($invoice, 'viewed');
	}

	public function onPaid($invoice)
	{
		$this->sendNotifications($invoice, 'paid');
	}

	private function sendNotifications($invoice, $type)
	{
		foreach ($invoice->account->users as $user)
		{
			if ($user->{'notify_' . $type})
			{
				$this->mailer->sendNotification($user, $invoice, $type);
			}
		}
	}
}