<?php

namespace App\Ninja\Intents\WebApp;

use App\Models\Account;
use App\Ninja\Intents\BaseIntent;

class NavigateToIntent extends BaseIntent
{
    public function process()
    {
        $location = $this->getField('Location');
        $location = str_replace(' ', '_', $location);

        if (in_array($location, array_merge(Account::$basicSettings, Account::$advancedSettings))) {
            $location = '/settings/' . $location;
        } elseif (in_array($location, ['report', 'reports'])) {
            $location = '/reports';
        } elseif ($location == 'settings') {
            $location = '/settings';
        } else {
            $location = '/dashboard';
        }

        return redirect($location);
    }
}
