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

            $this->error_msg = ctrans('texts.client_id_required');

            return false;
        }

        $unique_array = [];
        
        //todo optimize this into a single query
        foreach ($this->input['invoices'] as $invoice) {

            $unique_array[] = $invoice['invoice_id'];

            $inv = Invoice::whereId($invoice['invoice_id'])->first();

            if (! $inv) {

                $this->error_msg = ctrans('texts.invoice_not_found');

                return false;
            }

            if ($inv->client_id != $this->input['client_id']) {

                $this->error_msg = ctrans('texts.invoices_dont_match_client');

                return false;
            }

            if($inv->status_id == Invoice::STATUS_DRAFT && $invoice['amount'] <= $inv->amount){
                //catch here nothing to do - we need this to prevent the last elseif triggering
            }
            else if($inv->status_id == Invoice::STATUS_DRAFT && $invoice['amount'] > $inv->amount){

                $this->error_msg = 'Amount cannot be greater than invoice balance';

                return false;
            }
            else if($invoice['amount'] > $inv->balance) {

                $this->error_msg = ctrans('texts.amount_greater_than_balance');

                return false;
            }
        }

        if (! (array_unique($unique_array) == $unique_array)) {
            $this->error_msg = ctrans('texts.duplicate_invoices_submitted');

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
