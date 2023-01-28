<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules;

use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidCompanyGatewayFeesAndLimitsRule.
 */
class ValidCompanyGatewayFeesAndLimitsRule implements Rule
{
    use CompanyGatewayFeesAndLimitsSaver;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public $return_data;

    public function passes($attribute, $value)
    {
        $data = $this->validateFeesAndLimits($value);

        if (is_array($data)) {
            $this->return_data = $data;

            return false;
        } else {
            return true;
        }
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->return_data[0].' is not a valid '.$this->return_data[1];
    }
}
