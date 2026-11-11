<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Company;

use App\DataMapper\CompanyDefaults\CountryDefaults;
use App\Factory\TaxRateFactory;
use App\Models\Company;
use App\Models\User;

class CompanyService
{
    public function __construct(public Company $company) {}

    public function localizeCompany(User $user): void
    {
        try {

            $country_id = $this->company->settings->country_id;
            $defaults = CountryDefaults::get($country_id);

            foreach ($defaults['tax_rates'] as $tax) {
                $tax_rate = TaxRateFactory::create($this->company->id, $user->id);
                $tax_rate->fill($tax);
                $tax_rate->save();
            }

        } catch (\Exception $e) {
            nlog("Exception:: CompanyService::localizeCompany::" . $e->getMessage());
        }

    }

}
