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

namespace App\Http\Requests\BankIntegration;

use App\Http\Requests\Request;
use App\Models\BankIntegration;
use App\Utils\Traits\MakesHash;

class StoreBankIntegrationRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create', BankIntegration::class);
    }

    public function rules()
    {
        $rules = [
            'bank_account_name' => 'required|min:3',
            'auto_sync' => 'sometimes|bool'
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if ((!array_key_exists('provider_name', $input) || strlen($input['provider_name']) == 0) && array_key_exists('bank_account_name', $input)) {
            $input['provider_name'] = $input['bank_account_name'];
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [];
    }
}
