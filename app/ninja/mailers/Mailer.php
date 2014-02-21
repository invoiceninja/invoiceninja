<?php namespace ninja\mailers;

use Mail;

abstract class Mailer {

	public function sendTo($toEmail, $fromEmail, $subject, $view, $data = [])
	{
		$views = [
			'emails.'.$view.'_html',
			'emails.'.$view.'_text'
		];

		//$view = 'emails.' . $view;

		Mail::queue($views, $data, function($message) use ($toEmail, $fromEmail, $subject)
		{			
			$message->to($toEmail)->replyTo($fromEmail)->subject($subject);
		});		
	}
}