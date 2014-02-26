<?php

class HomeController extends BaseController {

	protected $layout = 'master';

	public function showWelcome()
	{
		return View::make('splash');
	}

	public function showAboutUs()
	{
		return View::make('about_us');
	}

	public function showContactUs()
	{
		return View::make('contact_us');
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