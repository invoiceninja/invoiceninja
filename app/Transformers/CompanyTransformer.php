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
use App\Models\User;
use App\Transformers\CompanyUserTransformer;
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
    ];


    /**
     * @param Company $company
     *
     * @return array
     */
    public function transform(Company $company)
    {
        return [
            'id' => $this->encodePrimaryKey($company->id),
            'name' => $company->name,
            'logo' => $company->logo,
            'company_key' => $company->company_key,
            'address1' => $company->address1,
            'address2' => $company->address2,
            'city' => $company->city,
            'state' => $company->state,
            'postal_code' => $company->postal_code,
            'work_phone' => $company->work_phone,
            'work_email' => $company->work_email,
            'country_id' => (int) $company->country_id,
            'vat_number' => $company->vat_number,
            'id_number' => $company->id_number,
            'size_id' => (int) $company->size_id,
            'industry_id' => (int) $company->industry_id,
            'settings' => $company->settings,
            'updated_at' => $company->updated_at,
            'deleted_at' => $company->deleted_at,
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
