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
    }

    public function onLogin()
    {
        $account = Auth::user()->account;
        $account->last_login = Carbon::now()->toDateTimeString();
        $account->save();

        Event::fire('user.refresh');
    }

    public function onRefresh()
    {
        Auth::user()->account->loadLocalizationSettings();
    }
}
