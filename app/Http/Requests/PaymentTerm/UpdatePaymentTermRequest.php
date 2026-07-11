<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\PaymentTerm;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class UpdatePaymentTermRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->payment_term);
    }

    public function rules()
    {
        return [
            'num_days' => ['required', 'integer', 'min:-1'],
            'cash_discount_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cash_discount_percent' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }
}
