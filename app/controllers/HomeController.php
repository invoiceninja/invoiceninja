<?php

class HomeController extends BaseController {

	protected $layout = 'master';

	public function showWelcome()
	{
		return View::make('splash');
	}
}