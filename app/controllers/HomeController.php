<?php

class HomeController extends BaseController {

	protected $layout = 'master';

	public function showWelcome()
	{
		return View::make('splash');
	}

	public function logError()
	{
		$count = Session::get('error_count', 0);
		Session::put('error_count', ++$count);
		if ($count > LOGGED_ERROR_LIMIT) return 'logged';

		$data = [
			'context' => 'JavaScript',
			'user_id' => Auth::check() ? Auth::user()->id : 0,
			'url' => Input::get('url'),
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'ip' => Request::getClientIp(),
			'count' => $count
		];

		Log::error(Input::get('error'), $data);

		return 'logged';
	}
}