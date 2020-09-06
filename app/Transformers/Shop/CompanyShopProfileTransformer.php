<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers\Shop;

use App\Models\Account;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Expense;
use App\Models\GroupSetting;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Webhook;
use App\Transformers\CompanyLedgerTransformer;
use App\Transformers\CompanyTokenHashedTransformer;
use App\Transformers\CompanyTokenTransformer;
use App\Transformers\CreditTransformer;
use App\Transformers\EntityTransformer;
use App\Transformers\PaymentTermTransformer;
use App\Transformers\TaskTransformer;
use App\Transformers\WebhookTransformer;
use App\Utils\Traits\MakesHash;

/**
 * Class CompanyShopProfileTransformer.
 */
class CompanyShopProfileTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected $availableIncludes = [
    ];

    /**
     * @param Company $company
     *
     * @return array
     */
    public function transform(Company $company)
    {
        $std = new \stdClass;

        return [
            'company_key' => (string) $company->company_key ?: '',
            'settings' => $this->trimCompany($company),
        ];
    }

    private function trimCompany($company)
    {
        $std = new \stdClass;

        $trimmed_company_settings = [
            'custom_fields' => $company->custom_fields ?: $std,
            'custom_value1' => $company->settings->custom_value1,
            'custom_value2' => $company->settings->custom_value2,
            'custom_value3' => $company->settings->custom_value3,
            'custom_value4' => $company->settings->custom_value4,
            'name' => $company->settings->name,
            'company_logo' => $company->settings->company_logo,
            'website' => $company->settings->website,
            'address1' => $company->settings->address1,
            'address2' => $company->settings->address2,
            'city' => $company->settings->city,
            'state' => $company->settings->state,
            'postal_code' => $company->settings->postal_code,
            'phone' => $company->settings->phone,
            'email' => $company->settings->email,
            'country_id' => $company->settings->country_id,
            'vat_number' => $company->settings->vat_number,
        ];

        $new_settings = new \stdClass;

        foreach ($trimmed_company_settings as $key => $value) {
            $new_settings->{$key} = $value;
        }

        return $new_settings;
    }
}
