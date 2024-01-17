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

namespace App\Http\ValidationRules\Company;

use App\Libraries\MultiDB;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidCompanyQuantity.
 */
class ValidSubdomain implements Rule
{
    public function __construct()
    {
    }

    public function passes($attribute, $value)
    {
        if (empty($value)) {
            return true;
        }

        return MultiDB::checkDomainAvailable($value);
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.subdomain_taken');
    }
}
