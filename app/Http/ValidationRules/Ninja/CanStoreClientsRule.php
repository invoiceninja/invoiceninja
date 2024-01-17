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
    /**
     * @var \App\Models\Company $company
     */
    public Company $company;

    public function __construct(public int $company_id)
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->company = Company::query()->find($this->company_id);

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
