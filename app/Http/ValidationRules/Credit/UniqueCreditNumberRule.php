<?php
/**
 * Credit Ninja (https://creditninja.com).
 *
 * @link https://github.com/creditninja/creditninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://creditninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Credit;

use App\Models\Credit;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueCreditNumberRule.
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
        return ctrans('texts.credit_number_taken');
    }

    /**
     * @return bool
     */
    private function checkIfCreditNumberUnique(): bool
    {
        $credit = Credit::query()->where('client_id', $this->input['client_id'])
                        ->where('number', $this->input['number'])
                        ->withTrashed()
                        ->exists();

        if ($credit) {
            return false;
        }

        return true;
    }
}
