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

	public function showIndex()
	{
		if (Utils::isNinja())
		{
			return View::make('public.splash');
		}
		else
		{
			if (Account::count() == 0)
			{
				return Redirect::to('/invoice_now');
			}
			else
			{
				return Redirect::to('/login');
			}
		}
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
    public function showTestimonials()
	{
		return View::make('public.testimonials');
	}


	public function doContactUs()
	{
		$email = Input::get('email');
		$name = Input::get('name');
		$message = Input::get('message');

		$data = [		
			'text' => $message
		];

		$this->mailer->sendTo(CONTACT_EMAIL, $email, $name, 'Invoice Ninja Feedback', 'contact', $data);

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

	public function showCompare()
	{
		return View::make('public.compare');	
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

	public function newsFeed($userType, $version)
	{		
		$response = Utils::getNewsFeedResponse($userType);

		return Response::json($response);
	}

	public function hideMessage()
	{		
		if (Auth::check() && Session::has('news_feed_id')) {
			$newsFeedId = Session::get('news_feed_id');
			if ($newsFeedId != NEW_VERSION_AVAILABLE && $newsFeedId > Auth::user()->news_feed_id) {
				$user = Auth::user();
				$user->news_feed_id = $newsFeedId;
				$user->save();
			}

			Session::forget('news_feed_message');
		}

		return 'success';
	}

	public function logError()
	{
		return Utils::logError(Input::get('error'), 'JavaScript');
	}
}