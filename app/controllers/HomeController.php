<?php

class HomeController extends BaseController {

	protected $layout = 'master';

	public function showWelcome()
	{
		return View::make('splash');
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