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

        return auth()->user()->can('create', User::class);

    }

    public function rules()
    {

        $this->sanitize();

        return [
            'first_name' => 'required|string|max:100',
            'last_name' =>  'required|string:max:100',
            'email' => new NewUniqueUserRule(),
            'is_admin' => 'required',
        ];

    }

    public function sanitize()
    {
        $input = $this->all();

        if(!isset($input['is_admin']))
            $input['is_admin'] = null;

        if(!isset($input['permissions']))
            $input['permissions'] = json_encode([]);

        if(!isset($input['settings']))
            $input['settings'] = json_encode(DefaultSettings::userSettings());

        $this->replace($input); 
    }


}