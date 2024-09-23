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

namespace App\Http\ValidationRules;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidPayableInvoicesRule.
 */
class ValidPayableInvoicesRule implements Rule
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
        /*If no invoices has been sent, then we apply the payment to the client account*/
        $invoices = [];

        if (is_array($value)) {
            $invoices = Invoice::query()->withTrashed()->whereIn('id', array_column($value, 'invoice_id'))->company()->get();
        }

        foreach ($invoices as $invoice) {
            if (! $invoice->isPayable()) {
                $this->error_msg = ctrans('texts.one_or_more_invoices_paid');

                return false;
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
