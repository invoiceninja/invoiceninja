<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
        $company = Company::find($this->company_id);

        return $company->clients->count() < config('ninja.quotas.free.clients');
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.limit_clients', ['count' => config('ninja.quotas.free.clients')]);
    }
}
