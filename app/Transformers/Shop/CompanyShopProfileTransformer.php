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

namespace App\Transformers\Shop;

use App\Models\Company;
use App\Transformers\EntityTransformer;
use App\Utils\Traits\MakesHash;
use stdClass;

/**
 * Class CompanyShopProfileTransformer.
 */
class CompanyShopProfileTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected array $defaultIncludes = [
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
    ];

    /**
     * @param Company $company
     *
     * @return array
     */
    public function transform(Company $company)
    {
        $std = new stdClass();

        return [
            'company_key' => (string) $company->company_key ?: '',
            'settings' => $this->trimCompany($company),
        ];
    }

    private function trimCompany($company)
    {
        $std = new stdClass();

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

        $new_settings = new stdClass();

        foreach ($trimmed_company_settings as $key => $value) {
            $new_settings->{$key} = $value;
        }

        return $new_settings;
    }
}
