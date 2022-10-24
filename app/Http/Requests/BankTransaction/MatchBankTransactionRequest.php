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
            '*.id' => 'bail|required',
            '*.invoice_ids' => 'nullable|string|sometimes',
            '*.ninja_category_id' => 'nullable|string|sometimes'
        ];

        $rules['*.vendor_id'] = 'bail|sometimes|exists:vendors,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';

        return $rules;

    }

    public function prepareForValidation()
    {
        $inputs = $this->all();
        
        nlog($inputs);

        foreach($inputs as $input)
        {
            nlog($input);

            if(isset($input['id']))
                $input['id'] = $this->decodePrimaryKey($input['id']);

            if(isset($input['ninja_category_id']) && strlen($input['ninja_category_id']) >= 1)
                $input['ninja_category_id'] = $this->decodePrimaryKey($input['ninja_category_id']);

            $input = $this->decodePrimaryKeys($input);
        }

        $this->replace($inputs);

    }
}
