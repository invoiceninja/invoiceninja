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
use App\Http\ValidationRules\ValidCreditsPresentRule;
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

        $invoices_total = 0;
        $credits_total = 0;

        if (isset($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {

            foreach ($input['invoices'] as $key => $value) {
                $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
                $invoices_total += $value['amount'];
            }

            //if(!isset($input['amount']) || )
        }

        if (isset($input['invoices']) && is_array($input['invoices']) === false) {
            $input['invoices'] = null;
        }

        if (isset($input['credits']) && is_array($input['credits']) !== false) {
            foreach ($input['credits'] as $key => $value) {

                if(array_key_exists('credit_id', $input['credits'][$key]))
                {
                    $input['credits'][$key]['credit_id'] = $this->decodePrimaryKey($value['credit_id']);
                    $credits_total += $value['amount'];
                }
            }
        }

        if (isset($input['credits']) && is_array($input['credits']) === false) {
            $input['credits'] = null;
        }

        if(!isset($input['amount'])){
            $input['amount'] = $invoices_total - $credits_total;
        }

        $this->replace($input);
    }

    public function rules()
    {
        $rules = [
            'amount' => 'numeric|required',
            'amount' => [new PaymentAmountsBalanceRule(),new ValidCreditsPresentRule()],
            'date' => 'required',
            'client_id' => 'required',
            'invoices.*.invoice_id' => 'required',
            'invoices.*.amount' => 'required',
            'credits.*.credit_id' => 'required',
            'credits.*.amount' => 'required',
            'invoices' => new ValidPayableInvoicesRule(),
            'number' => 'nullable',
        ];

        return $rules;
    }
}
