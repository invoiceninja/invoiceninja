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

namespace App\Http\ValidationRules\Payment;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidInvoicesRules.
 */
class ValidInvoicesRules implements Rule
{
    use MakesHash;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    private $error_msg;

    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        return $this->checkInvoicesAreHomogenous();
    }

    private function checkInvoicesAreHomogenous()
    {
        if (! array_key_exists('client_id', $this->input)) {
            $this->error_msg = 'Client id is required';

            return false;
        }

        $unique_array = [];
        
        //todo optimize this into a single query
        foreach ($this->input['invoices'] as $invoice) {
            $unique_array[] = $invoice['invoice_id'];

            $inv = Invoice::whereId($invoice['invoice_id'])->first();

            if (! $inv) {
                $this->error_msg = 'Invoice not found ';

                return false;
            }

            if ($inv->client_id != $this->input['client_id']) {
                $this->error_msg = 'Selected invoices are not from a single client';

                return false;
            }
        }

        if (! (array_unique($unique_array) == $unique_array)) {
            $this->error_msg = 'Duplicate invoices submitted.';

            return false;
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
