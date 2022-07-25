<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules;

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
        return ctrans('texts.amounts_do_not_balance');
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
                if (array_key_exists('amount', $credit)) {
                    $payment_amounts += $credit['amount'];
                }
            }
        }

        if (request()->input('invoices') && is_array(request()->input('invoices'))) {
            foreach (request()->input('invoices') as $invoice) {
                if (array_key_exists('amount', $invoice)) {
                    $invoice_amounts += $invoice['amount'];
                }
            }
        } else {
            return true;
        }

        return round($payment_amounts, 2) >= round($invoice_amounts, 2);
    }
}
