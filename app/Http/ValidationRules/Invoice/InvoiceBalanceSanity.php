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
        DB::connection(config('database.default'))->beginTransaction();

        $this->invoice = Invoice::on(config('database.default'))->withTrashed()->find($this->invoice->id);
        $this->invoice->line_items = $this->input['line_items'];
        $temp_invoice = $this->invoice->calc()->getTempEntity();

        DB::connection(config('database.default'))->rollBack();

        if ($temp_invoice->balance < 0) {
            $this->message = 'Invoice balance cannot go negative';

            return false;
        }

        return true;
    }
}
