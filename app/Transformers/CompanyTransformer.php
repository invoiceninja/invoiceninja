<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Transformers;


use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyUser;
use App\Models\GroupSetting;
use App\Models\TaxRate;
use App\Models\User;
use App\Transformers\CompanyGatewayTransformer;
use App\Transformers\CompanyUserTransformer;
use App\Transformers\GroupSettingTransformer;
use App\Transformers\TaxRateTransformer;
use App\Utils\Traits\MakesHash;

/**
 * Class AccountTranCompanyTransformersformer.
 */
class CompanyTransformer extends EntityTransformer
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
        'users',
        'account',
        'clients',
        'contacts',
        'invoices',
        'tax_rates',
        'products',
        'country',
        'timezone',
        'language',
        'expenses',
        'payments',
        'company_user',
        'groups',
        'company_gateways',
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
            'id' => (string)$this->encodePrimaryKey($company->id),
            'company_key' => (string)$company->company_key ?: '',
            'update_products' => (bool)$company->update_products,
            'fill_products' => (bool)$company->fill_products,
            'convert_products' => (bool)$company->convert_products,
            'custom_surcharge_taxes1' => (bool)$company->custom_surcharge_taxes1,
            'custom_surcharge_taxes2' => (bool)$company->custom_surcharge_taxes2,
            'custom_surcharge_taxes3' => (bool)$company->custom_surcharge_taxes3,
            'custom_surcharge_taxes4' => (bool)$company->custom_surcharge_taxes4,
            'enable_product_cost' => (bool)$company->enable_product_cost,
            'enable_product_quantity' => (bool)$company->enable_product_quantity,
            'default_quantity' => (bool)$company->default_quantity,
            'custom_fields' => $company->custom_fields ?: $std,
            'size_id' => (string) $company->size_id ?: '',
            'industry_id' => (string) $company->industry_id ?: '',
            'first_month_of_year' => (string) $company->first_month_of_year ?: '',
            'first_day_of_week' => (string) $company->first_day_of_week ?: '',
            'settings' => $company->settings ?: '',
            'updated_at' => (int)$company->updated_at,
            'deleted_at' => (int)$company->deleted_at,
        ];
    }

    public function includeCompanyUser(Company $company)
    {
        $transformer = new CompanyUserTransformer($this->serializer);

        return $this->includeItem($company->company_users->where('user_id', auth()->user()->id)->first(), $transformer, CompanyUser::class);
    }

    public function includeUsers(Company $company)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeCollection($company->users, $transformer, User::class);
    }

    public function includeCompanyGateways(Company $company)
    {
        $transformer = new CompanyGatewayTransformer($this->serializer);

        return $this->includeCollection($company->company_gateways, $transformer, CompanyGateway::class);
    }

    public function includeClients(Company $company)
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeCollection($company->clients, $transformer, Client::class);
    }

    public function includeGroups(Company $company)
    {
        $transformer = new GroupSettingTransformer($this->serializer);

        return $this->includeCollection($company->groups, $transformer, GroupSetting::class);        
    }

    public function includeInvoices(Company $company)
    {
        $transformer = new InvoiceTransformer($this->serializer);

        return $this->includeCollection($company->invoices, $transformer, Invoice::class);
    }

    public function includeAccount(Company $company)
    {

        $transformer = new AccountTransformer($this->serializer);

        return $this->includeItem($company->account, $transformer, Account::class);
    
    }

    public function includeTaxRates(Company $company)
    {

        $transformer = new TaxRateTransformer($this->serializer);

        return $this->includeCollection($company->tax_rates, $transformer, TaxRate::class);
    
    }
}
