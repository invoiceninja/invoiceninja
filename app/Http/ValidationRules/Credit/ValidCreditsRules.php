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

namespace App\Http\ValidationRules\Credit;

use App\Libraries\MultiDB;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidCreditsRules
 * @package App\Http\ValidationRules\Credit
 */
class ValidCreditsRules implements Rule
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
        return $this->checkCreditsAreHomogenous();
    }

    private function checkCreditsAreHomogenous()
    {
        if (!array_key_exists('client_id', $this->input)) {
            $this->error_msg = "Client id is required";
            return false;
        }

        $unique_array = [];

        foreach ($this->input['credits'] as $credit) {
            $unique_array[] = $credit['credit_id'];

            $cred = Credit::find($this->decodePrimaryKey($credit['credit_id']));

            if (!$cred) {
                $this->error_msg = "Credit not found ";
                return false;
            }

            if ($cred->client_id != $this->input['client_id']) {
                $this->error_msg = "Selected invoices are not from a single client";
                return false;
            }
        }

        if (!(array_unique($unique_array) == $unique_array)) {
            $this->error_msg = "Duplicate credits submitted.";
            return false;
        }


        if(count($this->input['credits']) >=1 && count($this->input['invoices']) == 0)
            $this->error_msg = "You must have an invoice set when using a credit";
            return false;

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
