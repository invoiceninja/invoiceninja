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

use App\Http\Requests\Request;
use App\Http\ValidationRules\UniqueUserRule;

class UpdateUserRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('edit', $this->user);

    }


    public function rules()
    {

        $input = $this->all();

        return [
            'first_name' => 'required|string|max:100',
            'last_name' =>  'required|string:max:100',
            'email' => ['required', new UniqueUserRule($this->user, $input['email'])],
        ];
    }

}