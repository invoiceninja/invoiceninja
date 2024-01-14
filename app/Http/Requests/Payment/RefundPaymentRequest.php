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
            // foreach($input['credits'] as $key => $credit)
            //     $input['credits'][$key]['credit_id'] = $this->decodePrimaryKey($credit['credit_id']);
        }

        $this->replace($input);
    }

    public function rules(): array
    {
        $input = $this->all();

        $rules = [
            'id' => 'bail|required', //@phpstan-ignore-line
            'id' => new ValidRefundableRequest($input),
            'amount' => 'numeric',
            'date' => 'required',
            'invoices.*.invoice_id' => 'required',
            'invoices.*.amount' => 'required',
            'invoices' => new ValidRefundableInvoices($input),
        ];

        return $rules;
    }

    public function payment(): ?\App\Models\Payment
    {
        $input = $this->all();

        return Payment::whereId($input['id'])->first();
    }
}
