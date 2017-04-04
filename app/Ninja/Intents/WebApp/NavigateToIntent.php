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

        $map = [
            'report' => 'reports',
            'settings' => ACCOUNT_COMPANY_DETAILS,
        ];

        if (isset($map[$location])) {
            $location = $map[$location];
        }

        if (in_array($location, array_merge(Account::$basicSettings, Account::$advancedSettings))) {
            $location = '/settings/' . $location;
        }

        return redirect($location);
    }
}
