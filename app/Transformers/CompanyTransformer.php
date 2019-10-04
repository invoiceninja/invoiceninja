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
use App\Models\CompanyUser;
use App\Models\GroupSetting;
use App\Models\User;
use App\Transformers\CompanyUserTransformer;
use App\Transformers\GroupSettingTransformer;
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
        'groups'
    ];


    /**
     * @param Company $company
     *
     * @return array
     */
    public function transform(Company $company)
    {
        return [
            'id' => (string)$this->encodePrimaryKey($company->id),
            'name' => (string)$company->name ?: '',
            'website' => (string)$company->website ?: '',
            'logo_url' => (string)$company->getLogo(),
            'company_key' => (string)$company->company_key ?: '',
            'address1' => (string)$company->address1 ?: '',
            'address2' => (string)$company->address2 ?: '',
            'city' => (string)$company->city ?: '',
            'state' => (string)$company->state ?: '',
            'postal_code' => (string)$company->postal_code ?: '',
            'phone' => (string)$company->phone ?: '',
            'email' => (string)$company->email ?: '',
            'country_id' => (string) $company->country_id ?: '',
            'vat_number' => (string)$company->vat_number ?: '',
            'id_number' => (string)$company->id_number ?: '',
            'size_id' => (string) $company->size_id ?: '',
            'industry_id' => (string) $company->industry_id ?: '',
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
}
