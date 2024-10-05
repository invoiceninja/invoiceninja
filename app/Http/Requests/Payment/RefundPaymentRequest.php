<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Payment\ValidRefundableRequest;
use App\Http\ValidationRules\ValidRefundableInvoices;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;

class RefundPaymentRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->isAdmin();
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! isset($input['gateway_refund'])) {
            $input['gateway_refund'] = false;
        }

        if (! isset($input['send_email'])) {
            $input['send_email'] = false;
        }

        if (isset($input['id'])) {
            $input['id'] = $this->decodePrimaryKey($input['id']);
        }

        if (isset($input['invoices'])) {
            foreach ($input['invoices'] as $key => $invoice) {
                $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($invoice['invoice_id']);
            }
        }

        if (isset($input['credits'])) {
            unset($input['credits']);
        }

        $this->replace($input);
    }

    public function rules(): array
    {
        $input = $this->all();

        $rules = [
            'id' => ['bail','required', new ValidRefundableRequest($input)],
            'amount' => ['numeric', 'max:99999999999999'],
            'date' => 'required',
            'invoices.*.invoice_id' => 'required|bail',
            'invoices.*.amount' => 'required|bail|gt:0',
            'invoices' => new ValidRefundableInvoices($input),
        ];

        return $rules;
    }

    public function payment(): ?\App\Models\Payment
    {
        $input = $this->all();
        /** @var \App\Models\Payment */
        return Payment::find($input['id']);
    }
}
