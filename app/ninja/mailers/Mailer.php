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
			$fromEmail = NINJA_FROM_EMAIL;

			$message->to($toEmail)->from($fromEmail, $fromName)->replyTo($replyEmail, $fromName)->subject($subject);
		});		
	}
}