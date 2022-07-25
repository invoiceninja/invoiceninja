<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\User;

use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class AttachableUser.
 */
class AttachableUser implements Rule
{
    public $message;

    public function __construct()
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkUserIsAttachable($value);
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * @param $user_id
     * @return bool
     */
    private function checkUserIsAttachable($email) : bool
    {
        if (empty($email)) {
            return false;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return true;
        }

        $user_already_attached = CompanyUser::query()
                                    ->where('user_id', $user->id)
                                    ->where('account_id', $user->account_id)
                                    ->where('company_id', auth()->user()->company()->id)
                                    ->exists();

        //If the user is already attached or isn't link to this account - return false
        if ($user_already_attached) {
            $this->message = ctrans('texts.user_duplicate_error');

            return false;
        }

        if ($user->account_id != auth()->user()->account_id) {
            $this->message = ctrans('texts.user_cross_linked_error');

            return false;
        }

        return true;
    }
}
