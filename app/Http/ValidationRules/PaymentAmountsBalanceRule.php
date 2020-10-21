<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class PaymentAmountsBalanceRule.
 */
class PaymentAmountsBalanceRule implements Rule
{
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->calculateAmounts();
    }

    /**
     * @return string
     */
    public function message()
    {
        return 'Amounts do not balance correctly.';
    }

    private function calculateAmounts() :bool
    {
        /*
         * Sometimes the request may not contain the amount or it may be zero,
         * and this is a valid use case, only compare the amounts if they
         * have been presented!
         */

        if (! request()->has('amount')) {
            return true;
        }

        if (request()->has('amount') && request()->input('amount') == 0) {
            return true;
        }

        $payment_amounts = 0;
        $invoice_amounts = 0;

        $payment_amounts += request()->input('amount');

        if (request()->input('credits') && is_array(request()->input('credits'))) {
            foreach (request()->input('credits') as $credit) {
                $payment_amounts += $credit['amount'];
            }
        }

        if (request()->input('invoices') && is_array(request()->input('invoices'))) {
            foreach (request()->input('invoices') as $invoice) {
                $invoice_amounts += $invoice['amount'];
            }
        } else {
            return true;
        } // if no invoices are present, then this is an unapplied payment, let this pass validation!

info("payment amounts = {$payment_amounts}");
info("invoice amounts = {$invoice_amounts}");
        //return true;
        return  $payment_amounts >= $invoice_amounts;
    }
}
