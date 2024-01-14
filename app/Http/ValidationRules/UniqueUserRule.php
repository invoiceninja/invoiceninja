<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueUserRule.
 */
class UniqueUserRule implements Rule
{
    public $user;

    public $new_email;

    public function __construct($user, $new_email)
    {
        $this->user = $user;

        $this->new_email = $new_email;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        /* If the input has not changed, return early! */

        if ($this->user->email == $this->new_email) {
            return true;
        } else {
            return ! $this->checkIfEmailExists($value);
        } //if it exists, return false!
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
    private function checkIfEmailExists($email): bool
    {
        return MultiDB::checkUserEmailExists($email);
    }
}
