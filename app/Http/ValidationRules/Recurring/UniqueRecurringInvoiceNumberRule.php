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

namespace App\Http\ValidationRules\Recurring;

use App\Models\RecurringInvoice;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueRecurringInvoiceNumberRule.
 */
class UniqueRecurringInvoiceNumberRule implements Rule
{
    public $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkIfInvoiceNumberUnique(); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.recurring_invoice_number_taken', ['number' => $this->input['number']]);
    }

    /**
     * @return bool
     */
    private function checkIfInvoiceNumberUnique(): bool
    {
        if (empty($this->input['number'])) {
            return true;
        }

        $invoice = RecurringInvoice::query()->where('client_id', $this->input['client_id'])
                        ->where('number', $this->input['number'])
                        ->withTrashed()
                        ->exists();

        if ($invoice) {
            return false;
        }

        return true;
    }
}
