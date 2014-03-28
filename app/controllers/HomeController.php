<?php

use ninja\mailers\Mailer;

class HomeController extends BaseController {

	protected $layout = 'master';
	protected $mailer;

	public function __construct(Mailer $mailer)
	{
		parent::__construct();

		$this->mailer = $mailer;
	}	

	public function showWelcome()
	{
		return View::make('public.splash');
	}

	public function showAboutUs()
	{
		return View::make('public.about_us');
	}

	public function showContactUs()
	{
		return View::make('public.contact_us');
	}

	public function showTerms()
	{
		return View::make('public.terms');
	}

	public function doContactUs()
	{
		$email = Input::get('email');
		$name = Input::get('name');
		$message = Input::get('message');

		$data = [		
			'name' => $name,
			'email' => $email,
			'text' => $message
		];

		$this->mailer->sendTo(CONTACT_EMAIL, CONTACT_EMAIL, CONTACT_NAME, 'Invoice Ninja Feedback', 'contact', $data);

		Session::flash('message', 'Successfully sent message');
		return Redirect::to('/contact');
	}

	public function showComingSoon()
	{
		return View::make('coming_soon');	
	}

	public function logError()
	{
		return Utils::logError(Input::get('error'), 'JavaScript');
	}
}