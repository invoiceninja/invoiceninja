<?php
/**
 * Credit Ninja (https://creditninja.com)
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2020. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules\Credit;

use App\Libraries\MultiDB;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueCreditNumberRule
 * @package App\Http\ValidationRules
 */
class UniqueCreditNumberRule implements Rule
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
        return $this->checkIfCreditNumberUnique(); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return "Credit number already taken";
    }

    /**
     * @param $email
     *
     * //off,when_sent,when_paid
     * 
     * @return bool
     */
    private function checkIfCreditNumberUnique() : bool
    {

        $credit = Credit::where('client_id', $this->input['client_id'])
                        ->where('number', $this->input['number'])
                        ->withTrashed()
                        ->exists();

        if($credit)
            return false;

        return true;
    }
}
