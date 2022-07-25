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

namespace App\Http\Requests\Account;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Account\BlackListRule;
use App\Http\ValidationRules\Account\EmailBlackListRule;
use App\Http\ValidationRules\NewUniqueUserRule;
use App\Utils\Ninja;

class UpdateAccountRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return (auth()->user()->isAdmin() || auth()->user()->isOwner()) && (int) $this->account->id === auth()->user()->account_id;
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
