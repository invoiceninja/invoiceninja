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

namespace App\Http\ValidationRules\Ninja;

use App\Models\Company;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class CanStoreClientsRule.
 */
class CanStoreClientsRule implements Rule
{
    public $company_id;

    public $company;

    public function __construct($company_id)
    {
        $this->company_id = $company_id;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->company = Company::find($this->company_id);

        return $this->company->clients()->count() < $this->company->account->hosted_client_count;
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.limit_clients', ['count' => $this->company->account->hosted_client_count]);
    }
}
