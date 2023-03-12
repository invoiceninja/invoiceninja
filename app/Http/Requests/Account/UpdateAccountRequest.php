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

namespace App\Http\Requests\Account;

use App\Http\Requests\Request;

class UpdateAccountRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (auth()->user()->isAdmin() || auth()->user()->isOwner()) && ($this->account->id == auth()->user()->token()->account_id);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'set_react_as_default_ap' => 'required|bail|bool',
        ];
    }

    /* Only allow single field to update account table */
    public function prepareForValidation()
    {
        $input = $this->all();

        $cleaned_input = array_intersect_key($input, array_flip(['set_react_as_default_ap']));

        $this->replace($cleaned_input);
    }
}
