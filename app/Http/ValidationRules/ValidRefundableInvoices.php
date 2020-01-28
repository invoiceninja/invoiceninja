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

namespace App\Http\ValidationRules;

use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidRefundableInvoices
 * @package App\Http\ValidationRules
 */
class ValidRefundableInvoices implements Rule
{
    use MakesHash;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    
    private $error_msg;

    public function passes($attribute, $value)
    {
        $payment = Payment::whereId($this->decodePrimaryKey(request()->input('id')))->first();

        if($request->has('refunded') && ($request->input('refunded') > ($payment->amount - $payment->refunded))){
            $this->error_msg = "Attempting to refunded more than payment amount, enter a value equal to or lower than the payment amount of ". $payment->amount;
            return false;
        }

        /*If no invoices has been sent, then we apply the payment to the client account*/
        $invoices = [];

        if (is_array($value)) {
            $invoices = Invoice::whereIn('id', array_column($value, 'invoice_id'))->company()->get();
        }
        else
            return true;

        foreach ($invoices as $invoice) {
            if (! $invoice->isRefundable()) {
                $this->error_msg = "One or more of these invoices have been paid";
                return false;
            }


            foreach ($value as $val) {
               if ($val['invoice_id'] == $invoice->id) {

                    if($val['refunded'] > ($invoice->amount - $invoice->balance))
                        $this->error_msg = "Attempting to refund more than is possible for an invoice";
                    return false;
               }
            }

        }



        return true;
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->error_msg;
    }
}
