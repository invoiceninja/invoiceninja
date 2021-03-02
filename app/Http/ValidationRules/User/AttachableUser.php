<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
        return "Cannot add the same user to the same company";
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

        if(!$user)
            return true;

        $user_already_attached =  CompanyUser::query()
                                    ->where('user_id', $user->id)
                                    ->where('account_id',$user->account_id)
                                    ->where('company_id', auth()->user()->company()->id)
                                    ->withTrashed()
                                    ->exists();

        if($user_already_attached)
            return false;


        return true;
    }
}
