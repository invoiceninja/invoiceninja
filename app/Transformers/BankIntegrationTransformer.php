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

namespace App\Transformers;

use App\Models\Account;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Utils\Traits\MakesHash;

/**
 * Class BankIntegrationTransformer.
 */
class BankIntegrationTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected array $defaultIncludes = [
        //'default_company',
        //'user',
        //'company_users'
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'company',
        'account',
        'bank_transactions',
    ];

    /**
     * @param BankIntegration $bank_integration
     * @return array
     */
    public function transform(BankIntegration $bank_integration)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($bank_integration->id),
            'provider_name' => (string) $bank_integration->provider_name ?: '',
            'provider_id' => (int) $bank_integration->provider_id ?: 0,
            'bank_account_id' => (int) $bank_integration->bank_account_id ?: 0,
            'bank_account_name' => (string) $bank_integration->bank_account_name ?: '',
            'bank_account_number' => (string) $bank_integration->bank_account_number ?: '',
            'bank_account_status' => (string) $bank_integration->bank_account_status ?: '',
            'bank_account_type' => (string) $bank_integration->bank_account_type ?: '',
            'nordigen_institution_id' => (string) $bank_integration->nordigen_institution_id ?: '',
            'balance' => (float) $bank_integration->balance ?: 0,
            'currency' => (string) $bank_integration->currency ?: '',
            'nickname' => (string) $bank_integration->nickname ?: '',
            'from_date' => (string) $bank_integration->from_date ?: '',
            'is_deleted' => (bool) $bank_integration->is_deleted,
            'disabled_upstream' => (bool) $bank_integration->disabled_upstream,
            'auto_sync' => (bool) $bank_integration->auto_sync,
            'created_at' => (int) $bank_integration->created_at,
            'updated_at' => (int) $bank_integration->updated_at,
            'archived_at' => (int) $bank_integration->deleted_at,
            'integration_type' => (string) $bank_integration->integration_type ?: '',
        ];
    }

    public function includeAccount(BankIntegration $bank_integration)
    {
        $transformer = new AccountTransformer($this->serializer);

        return $this->includeItem($bank_integration->account, $transformer, Account::class);
    }

    public function includeCompany(BankIntegration $bank_integration)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($bank_integration->company, $transformer, Company::class);
    }

    public function includeBankTransactions(BankIntegration $bank_integration)
    {
        $transformer = new BankTransactionTransformer($this->serializer);

        return $this->includeCollection($bank_integration->transactions, $transformer, BankTransaction::class);
    }
}
