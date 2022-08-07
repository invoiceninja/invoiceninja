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

namespace App\Http\ValidationRules\Client;

use App\Models\Country;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueCreditNumberRule.
 */
class CountryCodeExistsRule implements Rule
{
    public function __construct()
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkIfCodeExists($value); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return 'Country code does not exist';
    }

    /**
     * @return bool
     */
    private function checkIfCodeExists($value) : bool
    {
        $country = Country::where('iso_3166_2', $value)
                        ->orWhere('iso_3166_3', $value)
                        ->exists();

        if ($country) {
            return true;
        }

        return false;
    }
}
