<?php namespace ninja\mailers;

use Mail;
use Utils;

class Mailer {

	public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
	{
		$views = [
			'emails.'.$view.'_html',
			'emails.'.$view.'_text'
		];
		
		Mail::send($views, $data, function($message) use ($toEmail, $fromEmail, $fromName, $subject)
		{
			$replyEmail = $fromEmail;

			// We're unable to set the true fromEmail for emails sent from Yahoo or AOL accounts 
			// http://blog.mandrill.com/yahoos-recent-dmarc-changes-and-how-that-impacts-senders.html
			if (strpos($fromEmail, '@yahoo.') !== false || strpos($fromEmail, '@aol.') !== FALSE)			
			{
				$fromEmail = CONTACT_EMAIL;
			}

			$message->to($toEmail)->from($fromEmail, $fromName)->sender($fromEmail, $fromName)
				->replyTo($replyEmail, $fromName)->subject($subject);
		});		
	}
}