<?php

class UserEventHandler
{
	public function subscribe($events)
	{
		$events->listen('user.signup', 'UserEventHandler@onSignup');
		$events->listen('user.login', 'UserEventHandler@onLogin');		

		$events->listen('user.refresh', 'UserEventHandler@onRefresh');		
	}

	public function onSignup()
	{
		dd('user signed up');
	}

	public function onLogin()
	{
        $account = Account::findOrFail(Auth::user()->account_id);
        $account->last_login = Carbon::now()->toDateTimeString();
        $account->save();

        Event::fire('user.refresh');
	}

	public function onRefresh()
	{
		$user = User::whereId(Auth::user()->id)->with('account', 'account.date_format', 'account.datetime_format', 'account.timezone')->firstOrFail();
		$account = $user->account;

		Session::put(SESSION_TIMEZONE, $account->timezone ? $account->timezone->name : DEFAULT_TIMEZONE);
		Session::put(SESSION_DATE_FORMAT, $account->date_format ? $account->date_format->format : DEFAULT_DATE_FORMAT);
		Session::put(SESSION_DATE_PICKER_FORMAT, $account->date_format ? $account->date_format->picker_format : DEFAULT_DATE_PICKER_FORMAT);
		Session::put(SESSION_DATETIME_FORMAT, $account->datetime_format ? $account->datetime_format->format : DEFAULT_DATETIME_FORMAT);			
	}
}