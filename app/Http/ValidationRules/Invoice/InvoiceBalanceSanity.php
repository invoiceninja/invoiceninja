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

namespace App\Http\ValidationRules\Invoice;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * Class LockedInvoiceRule.
 */
class InvoiceBalanceSanity implements Rule
{
    public $invoice;

    public $input;

    private $message;


    public function __construct(Invoice $invoice, $input)
    {
        $this->invoice = $invoice;
        $this->input = $input;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkIfInvoiceBalanceIsSane(); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * @return bool
     */
    private function checkIfInvoiceBalanceIsSane() : bool
    {

        $this->invoice->line_items = $this->input['line_items'];

        DB::beginTransaction();
 
            $temp_invoice = $this->invoice->calc()->getTempEntity();
 
        DB::rollBack();

        if($temp_invoice->balance < 0){
            $this->message = 'Invoice balance cannot go negative';
            return false;
        }


        return true;

    }
}
