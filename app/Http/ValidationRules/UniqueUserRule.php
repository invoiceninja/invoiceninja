<?php

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueUserRule
 * @package App\Http\ValidationRules
 */
class UniqueUserRule implements Rule
{

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return ! $this->checkIfEmailExists($value); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.email_already_register');
    }

    /**
     * @param $email
     * @return bool
     */
    private function checkIfEmailExists($email) : bool
    {
        return MultiDB::checkUserEmailExists($email);
    }

}
