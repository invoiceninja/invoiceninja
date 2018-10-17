<?php

namespace App\Http\ValidationRules;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class UniqueUserRule implements Rule
{

    public function passes($attribute, $value)
    {
        return $this->checkIfEmailExists($value);
    }

    public function message()
    {
        return trans('texts.email_already_register');
    }

    private function checkIfEmailExists($email) : bool
    {
        if (config('auth.providers.users.driver') == 'eloquent') //default eloquent = single DB
        {
            return User::where('email', '=', $email)->count() == 0 ?? false; // true -> 0 emails found / false -> >=1 emails found
        }
        else { //multidb is active

            foreach (unserialize(MULTI_DBS) as $db) {

                if(User::on($db)->where('email', '=', $email)->count() >=1)
                    return false;

            }

            return true;

        }


    }

}
