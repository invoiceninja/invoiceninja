<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Factory;

use App\Models\User;

class UserFactory
{
    public static function create() :User
    {
        $user = new User;

        $user->first_name = '';
        $user->last_name = '';
        $user->phone = '';
        $user->email = '';
        $user->last_login = now();
        $user->failed_logins = 0;
        $user->signature = '';
        $user->theme_id = 0;
        
        return $user;
    }
}
