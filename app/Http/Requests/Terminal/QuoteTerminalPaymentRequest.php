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

namespace App\Http\Requests\Terminal;

use App\Models\CompanyGateway;
use App\Models\Payment;
use Illuminate\Validation\Rule;

class QuoteTerminalPaymentRequest extends TerminalRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->terminalAvailable()
            && $user->can('create', Payment::class)
            && $user->can('view', $this->invoice);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'company_gateway_id' => ['bail', 'required', Rule::exists('company_gateways', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)],
            'amount' => 'bail|required|numeric|gt:0',
            'apply_fee' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty() && !$this->companyGateway()?->supportsCardPresent()) {
                $validator->errors()->add('company_gateway_id', ctrans('texts.terminal_not_enabled'));
            }
        });
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['company_gateway_id'])) {
            $input['company_gateway_id'] = $this->decodePrimaryKey($input['company_gateway_id']);
        }

        $input['apply_fee'] = filter_var($input['apply_fee'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->replace($input);
    }

    public function companyGateway(): ?CompanyGateway
    {
        return CompanyGateway::query()->find($this->input('company_gateway_id'));
    }
}
