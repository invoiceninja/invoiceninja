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

namespace App\Http\ValidationRules\Company;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidCompanyQuantity.
 */
class ValidSubdomain implements Rule
{
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        if (empty($input['subdomain'])) {
            return true;
        }

        return MultiDB::checkDomainAvailable($input['subdomain']);
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.subdomain_taken');
    }
}
