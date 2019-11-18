<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidPayableInvoicesRule
 * @package App\Http\ValidationRules
 */
class ValidPayableInvoicesRule implements Rule
{
    use MakesHash;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {

        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($value,'hashed_id')))->get();

        foreach ($invoices as $invoice) {

        if(! $invoice->isPayable())
            return false;
    
        }

        return true;
    }

    /**
     * @return string
     */
    public function message()
    {
        return "One or more of these invoices have been paid";
    }

}
