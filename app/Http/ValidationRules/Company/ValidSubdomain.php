<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Company;

use App\Libraries\MultiDB;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class ValidSubdomain.
 */
class ValidSubdomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (empty($value)) {
            return;
        }

        if (!MultiDB::checkDomainAvailable($value)) {
            $fail(ctrans('texts.subdomain_taken'));
        }
    }
}
