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

namespace App\Http\Requests\Report;

use App\Http\Requests\Request;

class ProfitLossRequest extends Request
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
        return [
            'start_date' => 'string|date',
            'end_date' => 'string|date',
            'is_income_billed' => 'required|bail|bool',
            'is_expense_billed' => 'required|bail|bool',
            'include_tax' => 'required|bail|bool',
            'date_range' => 'sometimes|string',
            'send_email' => 'bool',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! array_key_exists('date_range', $input)) {
            $input['date_range'] = 'all';
        }

        $this->replace($input);
    }
}
