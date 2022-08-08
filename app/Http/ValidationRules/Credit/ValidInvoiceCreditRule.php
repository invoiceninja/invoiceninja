<?php
/**
 * Credit Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Credit;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidInvoiceCreditRule.
 */
class ValidInvoiceCreditRule implements Rule
{
    public $error_message;

    public function __construct()
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkIfCreditInvoiceValid($value); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->error_message;
    }

    /**
     * @return bool
     */
    private function checkIfCreditInvoiceValid($value) : bool
    {
        $invoice = Invoice::withTrashed()->find($value);

        if ($invoice->balance >= $invoice->amount) {
            $this->error_message = 'Cannot reverse an invoice with no payment applied.';

            return false;
        }

        $existing_credit_amounts = $invoice->credits()->sum('amount');

        if ($this->sumCredit() > ($invoice->amount - $invoice->balance - $existing_credit_amounts)) {
            $this->error_message = 'Credit cannot exceed the payment / credits already applied to invoice.';

            return false;
        }

        return true;
    }

    private function sumCredit()
    {
        $cost = 0;

        foreach (request()->input('line_items') as $item) {
            $cost += $item['cost'] * $item['quantity'];
        }

        return $cost;
    }
}
