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

namespace App\Http\ValidationRules;

use App\Utils\Traits\ClientGroupSettingsSaver;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidClientGroupSettingsRule.
 */
class ValidClientGroupSettingsRule implements Rule
{
    use ClientGroupSettingsSaver;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public $return_data;

    public function passes($attribute, $value)
    {
        $data = $this->validateSettings($value);

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
