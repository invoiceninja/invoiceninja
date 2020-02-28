<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules\Payment;

use App\Libraries\MultiDB;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidInvoicesRules
 * @package App\Http\ValidationRules\Payment
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

        if(!array_key_exists('client_id', $this->input)){
            \Log::error("Client id is required");
            $this->error_msg = "Client id is required";
            return false;
        }

    	foreach($this->input['invoices'] as $invoice)
    	{
            $invoice = Invoice::whereId($invoice)->first();

            if(!$invoice){
                $this->error_msg = "Invoice not found ";
                return false;
            }

    		if($invoice->client_id != $this->input['client_id']){
                $this->error_msg = "Selected invoices are not from a single client";
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
