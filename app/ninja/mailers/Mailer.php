<?php namespace ninja\mailers;

use Mail;

abstract class Mailer {

	public function sendTo($toEmail, $fromEmail, $subject, $view, $data = [])
	{
		$views = [
			'html' => 'emails.'.$view.'_html',
			'text' => 'emails.'.$view.'_text'
		];
		
		Mail::queue($views, $data, function($message) use ($toEmail, $fromEmail, $subject)
		{
			$message->to($toEmail)->replyTo($fromEmail)->subject($subject);
		});
	}
}