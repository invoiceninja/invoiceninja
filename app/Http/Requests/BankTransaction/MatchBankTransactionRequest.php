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

namespace App\Http\Requests\BankTransaction;

use App\Http\Requests\Request;
use App\Models\BankTransaction;

class MatchBankTransactionRequest extends Request
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

        $rules = [
            '*.id' => 'required|bail',
            '*.invoice_ids' => 'nullable|string|sometimes',
            '*.ninja_category_id' => 'nullable|string|sometimes'
        ];

        $rules['*.vendor_id'] = 'bail|sometimes|exists:vendors,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';

        return $rules;

    }

    public function prepareForValidation()
    {
        $inputs = $this->all();

        foreach($inputs as $input)
        {
            if(array_key_exists('id', $input))
                $input['id'] = $this->decodePrimaryKey($input['id']);

            if(array_key_exists('ninja_category_id', $input) && strlen($input['ninja_category_id']) >= 1)
                $input['ninja_category_id'] = $this->decodePrimaryKey($input['ninja_category_id']);

            $input = $this->decodePrimaryKeys($input);
        }

        $this->replace($inputs);

    }
}
