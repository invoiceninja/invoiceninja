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

namespace App\Http\Requests\User;

use App\DataMapper\DefaultSettings;
use App\Factory\UserFactory;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Ninja\CanAddUserRule;
use App\Http\ValidationRules\User\AttachableUser;
use App\Http\ValidationRules\ValidUserForCompany;
use App\Libraries\MultiDB;
use App\Models\User;
use App\Utils\Ninja;
use Illuminate\Validation\Rule;

class StoreUserRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $rules = [];

        $rules['first_name'] = 'required|string|max:100';
        $rules['last_name'] = 'required|string|max:100';

        if (config('ninja.db.multi_db_enabled')) {
            $rules['email'] = ['email', new ValidUserForCompany(), new AttachableUser()];
        } else {
            $rules['email'] = ['email', new AttachableUser()];
        }

        if (Ninja::isHosted()) {
            $rules['id'] = new CanAddUserRule();
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        //unique user rule - check company_user table for user_id / company_id  / account_id if none exist we can add the user. ELSE return false

        if (array_key_exists('email', $input)) {
            $input['email'] = trim($input['email']);
        }

        if (isset($input['company_user'])) {
            if (! isset($input['company_user']['is_admin'])) {
                $input['company_user']['is_admin'] = false;
            }

            if (! isset($input['company_user']['permissions'])) {
                $input['company_user']['permissions'] = '';
            }

            if (! isset($input['company_user']['settings'])) {
                //$input['company_user']['settings'] = DefaultSettings::userSettings();
                $input['company_user']['settings'] = null;
            }
        } else {
            $input['company_user'] = [
                //'settings' => DefaultSettings::userSettings(),
                'settings' => null,
                'permissions' => '',
            ];
        }

        $this->replace($input);
    }

    //@todo make sure the user links back to the account ID for this company!!!!!!
    public function fetchUser() :User
    {
        $user = MultiDB::hasUser(['email' => $this->input('email')]);

        if (! $user) {
            $user = UserFactory::create(auth()->user()->account->id);
        }

        return $user;
    }
}
