<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules;

use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class PaymentAppliedValidAmount.
 */
class PaymentAppliedValidAmount implements Rule
{
    use MakesHash;

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
        return ctrans('texts.insufficient_applied_amount_remaining');
    }

    private function calculateAmounts() :bool
    {
        $payment = Payment::whereId($this->decodePrimaryKey(request()->segment(4)))->company()->first();

        if (! $payment) {
            return false;
        }

        $payment_amounts = 0;
        $invoice_amounts = 0;

        $payment_amounts = $payment->amount - $payment->applied;

        if (request()->input('credits') && is_array(request()->input('credits'))) {
            foreach (request()->input('credits') as $credit) {
                $payment_amounts += $credit['amount'];
            }
        }

        if (request()->input('invoices') && is_array(request()->input('invoices'))) {
            foreach (request()->input('invoices') as $invoice) {
                $invoice_amounts += $invoice['amount'];
            }
        }

        return  $payment_amounts >= $invoice_amounts;
    }
}
