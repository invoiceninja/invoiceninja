<?php namespace Ninja\Mailers;

use Mail;

abstract class Mailer {

	public function sendTo($email, $subject, $view, $data = [])
	{
		$views = [
			'html' => 'emails.'.$view.'_html',
			'text' => 'emails.'.$view.'_text'
		];
		
		Mail::queue($views, $data, function($message) use($email, $subject)
		{
			$message->to($email)->subject($subject);
		});
	}
}