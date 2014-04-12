<?php namespace ninja\mailers;

use Mail;

class Mailer {

	public function sendTo($toEmail, $fromEmail, $fromName, $subject, $view, $data = [])
	{
		$views = [
			'emails.'.$view.'_html',
			'emails.'.$view.'_text'
		];
		
		Mail::send($views, $data, function($message) use ($toEmail, $fromEmail, $fromName, $subject)
		{			
			$message->to($toEmail)->from($fromEmail, $fromName)->sender($fromEmail, $fromName)
				->replyTo($fromEmail, $fromName)->returnPath($fromEmail)->subject($subject);
		});		
	}
}