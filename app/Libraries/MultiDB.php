<?php

namespace App\Libraries;

use App\Models\User;

class MultiDB
{

    public static function findUserWithEmail($email) : bool
    {
        if (config('auth.providers.users.driver') == 'eloquent') //default eloquent = single DB
        {
            return User::where(['email' => $email])->get()->count() == 0 ?? false; // true -> 0 emails found / false -> >=1 emails found
        }

            //multi-db active
            foreach (unserialize(MULTI_DBS) as $db)
            {
                if(User::on($db)->where(['email' => $email])->get()->count() >=1) // if user already exists, validation will fail
                    return false;
            }

            return true;

    }

}