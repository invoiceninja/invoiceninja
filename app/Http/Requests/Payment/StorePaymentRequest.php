<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Http\ValidationRules\ValidPayableInvoicesRule;
use App\Http\ValidationRules\PaymentAmountsBalanceRule;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;

class StorePaymentRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Payment::class);
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }


        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                $input['invoices'][$key]['id'] = $this->decodePrimaryKey($value['id']);
            }
        }


        if (isset($input['invoices']) && is_array($input['invoices']) === false) {
            $input['invoices'] = null;
        }

        $this->replace($input);
    }

    public function rules()
    {
        $rules = [
            'amount' => 'numeric|required',
            'amount' => new PaymentAmountsBalanceRule(),
            'date' => 'required',
            'client_id' => 'required',
            'invoices' => new ValidPayableInvoicesRule(),
            'number' => 'nullable|unique',
        ];

        return $rules;
    }
}
