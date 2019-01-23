<?php

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

class UniqueUserRule implements Rule
{

    public function passes($attribute, $value)
    {
        return ! $this->checkIfEmailExists($value); //if it exists, return false!
    }

    public function message()
    {
        return ctrans('texts.email_already_register');
    }

    private function checkIfEmailExists($email) : bool
    {
        return MultiDB::checkUserEmailExists($email);
    }

}
