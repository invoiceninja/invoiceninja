<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
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
        return auth()->user()->id === $this->id || auth()->user()->isAdmin();
    }

    public function rules()
    {
        $input = $this->all();
        $rules = [];

        if (isset($input['email'])) {
            $rules['email'] = ['sometimes', new UniqueUserRule($this->user, $input['email'])];
        }

        return $rules;
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['company_user']) && ! auth()->user()->isAdmin()) {
            unset($input['company_user']);
        }

        $this->replace($input);
    }
}
