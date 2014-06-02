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
		$data = [
			'title' => 'About Us',
			'description' => 'Invoice Ninja is an an open-source solution where you can create, customize, and generate invoices online for free using our templates!'
		];

		return View::make('public.about_us', $data);
	}

	public function showContactUs()
	{
		$data = [
			'title' => 'Contact Us',
			'description' => 'Contact us today and try out our free or premium hassle-free plans. Start your online invoicing today with Invoice Ninja!'
		];

		return View::make('public.contact_us', $data);
	}

	public function showTerms()
	{
		return View::make('public.terms');
	}

	public function showFaq()
	{
		return View::make('public.faq');
	}

	public function showFeatures()
	{
		return View::make('public.features');
	}

	public function showPlans()
	{
		$data = [
			'title' => 'Professional Invoicing Software & Templates',
			'description' => 'Invoice Ninja allows you to create and generate your own custom invoices. Choose from our professional invoice templates or customize your own with our pro plan.'
		];

		return View::make('public.plans', $data);
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

		$message = trans('texts.sent_message');
		Session::flash('message', $message);

		return View::make('public.contact_us');
	}

	public function showComingSoon()
	{
		return View::make('coming_soon');	
	}

	public function showSecurePayment()
	{
		return View::make('secure_payment');	
	}

	public function invoiceNow()
	{
		if (Auth::check())
		{
			return Redirect::to('invoices/create');				
		}
		else
		{
			return View::make('public.header', ['invoiceNow' => true]);
		}
	}

	public function logError()
	{
		return Utils::logError(Input::get('error'), 'JavaScript');
	}
}