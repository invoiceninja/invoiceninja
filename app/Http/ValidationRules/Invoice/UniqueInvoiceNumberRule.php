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

namespace App\Http\ValidationRules\Invoice;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Models\User;
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
        return 'Invoice number already taken';
    }

    /**
     * @param $email
     *
     * //off,when_sent,when_paid
     *
     * @return bool
     */
    private function checkIfInvoiceNumberUnique() : bool
    {
        if(empty($this->input['number']))
            return true;

        $invoice = Invoice::where('client_id', $this->input['client_id'])
                        ->where('number', $this->input['number'])
                        ->withTrashed()
                        ->exists();

        if ($invoice) {
            return false;
        }

        return true;
    }
}
