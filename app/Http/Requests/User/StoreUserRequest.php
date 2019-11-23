<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\User;

use App\DataMapper\DefaultSettings;
use App\Http\Requests\Request;
use App\Http\ValidationRules\NewUniqueUserRule;
use App\Models\User;

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

        $this->sanitize();

        return [
            'first_name' => 'required|string|max:100',
            'last_name' =>  'required|string:max:100',
            'email' => new NewUniqueUserRule(),
        ];

    }

    public function sanitize()
    {
        $input = $this->all();

        if(isset($input['company_user']))
        {
            if(!isset($input['company_user']['is_admin']))
                $input['company_user']['is_admin'] = false;

            if(!isset($input['company_user']['permissions']))
                $input['company_user']['permissions'] = '';

            if(!isset($input['company_user']['settings']))
                $input['company_user']['settings'] = json_encode(DefaultSettings::userSettings());

        }
        else{
            $input['company_user'] = [
                'settings' => json_encode(DefaultSettings::userSettings()),
                'permissions' => '',
            ];
        }

        $this->replace($input); 

        return $this->all();

    }



}