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
use Illuminate\Validation\Rule;

class BulkPaymentTermRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'action' => ['required', 'bail', 'in:archive,restore,delete'],
            'ids' => [
                'required',
                'bail',
                'array',
                'min:1',
                Rule::exists('payment_terms', 'id')->where('company_id', $user->company()->id),
            ],
            'ids.*' => ['bail', 'integer'],
        ];
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (isset($input['ids']) && is_array($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        $this->replace($input);
    }
}
