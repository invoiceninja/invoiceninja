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

namespace App\Jobs\Company;

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\CompanyDefaults\CountryDefaults;
use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Country;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateCompany
{
    use MakesHash;
    use Dispatchable;

    protected $request;

    protected $account;

    /**
     * Create a new job instance.
     *
     * @param array $request
     * @param $account
     */
    public function __construct(array $request, $account)
    {
        $this->request = $request;

        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return Company|null
     */
    public function handle(): ?Company
    {
        $settings = CompanySettings::defaults();

        $settings->name = $this->request['name'] ?? '';

        $country_id = $this->resolveCountry();
        $settings->country_id = $country_id;

        $company = new Company();
        $company->account_id = $this->account->id;
        $company->company_key = $this->createHash();
        $company->ip = request()->ip();
        $company->db = config('database.default');
        $company->enabled_modules = config('ninja.enabled_modules');
        $company->subdomain = $this->request['subdomain'] ?? MultiDB::randomSubdomainGenerator();
        $company->custom_fields = new \stdClass();
        $company->default_password_timeout = 1800000;
        $company->client_registration_fields = ClientRegistrationFields::generate();
        $company->markdown_email_enabled = true;
        $company->markdown_enabled = false;
        $company->tax_data = new TaxModel();

        if (Ninja::isHosted()) {
            $company->subdomain = MultiDB::randomSubdomainGenerator();
        } else {
            $company->subdomain = '';
        }

        $this->applyCountryDefaults($company, $settings, $country_id);

        return $company;
    }

    /**
     * Apply country-specific defaults to company and settings.
     */
    private function applyCountryDefaults(Company $company, mixed $settings, string $country_id): void
    {
        try {

            $defaults = CountryDefaults::get($country_id);

            if ($defaults['currency_id']) {
                $settings->currency_id = $defaults['currency_id'];
            }

            if ($defaults['timezone_id']) {
                $settings->timezone_id = $defaults['timezone_id'];
            }

            if ($defaults['language_id']) {
                $settings->language_id = $defaults['language_id'];
            }

            if ($defaults['e_invoice_type']) {
                $settings->e_invoice_type = $defaults['e_invoice_type'];
            }

            if ($defaults['translations']) {
                $translations = new \stdClass();
                foreach ($defaults['translations'] as $key => $value) {
                    $translations->{$key} = $value;
                }
                $settings->translations = $translations;
            }

            $company->enabled_tax_rates = $defaults['enabled_tax_rates'];
            $company->enabled_item_tax_rates = $defaults['enabled_item_tax_rates'];

            if ($defaults['custom_fields']) {
                $custom_fields = new \stdClass();
                foreach ($defaults['custom_fields'] as $key => $value) {
                    $custom_fields->{$key} = $value;
                }
                $company->custom_fields = $custom_fields;
            }

            $company->settings = $settings;
            $company->save();

        } catch (\Exception $e) {
            nlog("Exception:: CreateCompany::applyCountryDefaults::" . $e->getMessage());

            $company->settings = $settings;
            $company->save();
        }
    }

    /**
     * Resolve Country
     *
     * @return string
     */
    private function resolveCountry(): string
    {
        try {

            $ip = request()->ip();

            if (request()->hasHeader('cf-ipcountry')) {

                $c = Country::query()->where('iso_3166_2', request()->header('cf-ipcountry'))->first();

                if ($c) {
                    return (string) $c->id;
                }

            }

            $details = json_decode(file_get_contents("http://ip-api.com/json/{$ip}"));

            if ($details && property_exists($details, 'countryCode')) {

                $c = Country::query()->where('iso_3166_2', $details->countryCode)->first();

                if ($c) {
                    return (string) $c->id;
                }

            }
        } catch (\Exception $e) {
            nlog("Exception:: CreateCompany::" . $e->getMessage());
            nlog("Could not resolve country => {$e->getMessage()}");
        }

        return '840';

    }

}
