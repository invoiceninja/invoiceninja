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

use App\Models\BankTransactionRule;
use App\Models\Client;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;

/**
 * Class BankTransactionRuleTransformer.
 */
class BankTransactionRuleTransformer extends EntityTransformer
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
        'company',
        'vendor',
        'client',
        'expense_category',
    ];

    /**
     * @param BankTransactionRule $bank_transaction_rule
     * @return array
     */
    public function transform(BankTransactionRule $bank_transaction_rule)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($bank_transaction_rule->id),
            'name' => (string) $bank_transaction_rule->name,
            'rules' => $bank_transaction_rule->rules ?: (array) [],
            'auto_convert' => (bool) $bank_transaction_rule->auto_convert,
            'matches_on_all' => (bool) $bank_transaction_rule->matches_on_all,
            'applies_to' => (string) $bank_transaction_rule->applies_to,
            'client_id' => $this->encodePrimaryKey($bank_transaction_rule->client_id) ?: '',
            'vendor_id' => $this->encodePrimaryKey($bank_transaction_rule->vendor_id) ?: '',
            'category_id' => $this->encodePrimaryKey($bank_transaction_rule->category_id) ?: '',
            'is_deleted' => (bool) $bank_transaction_rule->is_deleted,
            'created_at' => (int) $bank_transaction_rule->created_at,
            'updated_at' => (int) $bank_transaction_rule->updated_at,
            'archived_at' => (int) $bank_transaction_rule->deleted_at,
        ];
    }

    public function includeCompany(BankTransactionRule $bank_transaction_rule)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($bank_transaction_rule->company, $transformer, Company::class);
    }

    public function includeClient(BankTransactionRule $bank_transaction_rule)
    {
        if (!$bank_transaction_rule->client) {
            return null;
        }

        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($bank_transaction_rule->client, $transformer, Client::class);
    }

    public function includeVendor(BankTransactionRule $bank_transaction_rule)
    {
        if (!$bank_transaction_rule->vendor) {
            return null;
        }

        $transformer = new VendorTransformer($this->serializer);

        return $this->includeItem($bank_transaction_rule->vendor, $transformer, Vendor::class);
    }

    public function includeExpenseCategory(BankTransactionRule $bank_transaction_rule)
    {
        if (!$bank_transaction_rule->expense_category) {
            return null;
        }

        $transformer = new ExpenseCategoryTransformer($this->serializer);

        return $this->includeItem($bank_transaction_rule->expense_category, $transformer, ExpenseCategory::class);
    }
}
