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

namespace App\Http\ValidationRules\Invoice;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueInvoiceNumberRule.
 */
class UniqueInvoiceNumberRule implements Rule
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
        return ctrans('texts.invoice_number_taken');
    }

    /**
     * @return bool
     */
    private function checkIfInvoiceNumberUnique(): bool
    {
        if (empty($this->input['number'])) {
            return true;
        }

        $invoice = Invoice::query()->where('client_id', $this->input['client_id'])
                        ->where('number', $this->input['number'])
                        ->withTrashed()
                        ->exists();

        if ($invoice) {
            return false;
        }

        return true;
    }
}
