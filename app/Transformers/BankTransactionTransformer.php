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

use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;

/**
 * Class BankTransactionTransformer.
 */
class BankTransactionTransformer extends EntityTransformer
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
        // 'expense',
        'payment',
        'vendor',
        'bank_account',
    ];

    /**
     * @param BankTransaction $bank_transaction
     * @return array
     */
    public function transform(BankTransaction $bank_transaction)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($bank_transaction->id),
            'user_id' => (string) $this->encodePrimaryKey($bank_transaction->user_id),
            'bank_integration_id' => (string) $this->encodePrimaryKey($bank_transaction->bank_integration_id),
            'transaction_id' => (int) $bank_transaction->transaction_id,
            'amount' => (float) $bank_transaction->amount ?: 0,
            'currency_id' => (string) $bank_transaction->currency_id ?: '1',
            'account_type' => (string) $bank_transaction->account_type ?: '',
            'category_id' => (int) $bank_transaction->category_id,
            'ninja_category_id' => (string) $this->encodePrimaryKey($bank_transaction->ninja_category_id) ?: '',
            'category_type' => (string) $bank_transaction->category_type ?: '',
            'date' => (string) $bank_transaction->date ?: '',
            'bank_account_id' => (int) $bank_transaction->bank_account_id,
            'status_id' => (string) $bank_transaction->status_id,
            'description' => (string) $bank_transaction->description ?: '',
            'participant' => (string) $bank_transaction->participant ?: '',
            'participant_name' => (string) $bank_transaction->participant_name ?: '',
            'base_type' => (string) $bank_transaction->base_type ?: '',
            'invoice_ids' => (string) $bank_transaction->invoice_ids ?: '',
            'expense_id' => (string) $bank_transaction->expense_id ?: '',
            'payment_id' => (string) $this->encodePrimaryKey($bank_transaction->payment_id) ?: '',
            'vendor_id' => (string) $this->encodePrimaryKey($bank_transaction->vendor_id) ?: '',
            'bank_transaction_rule_id' => (string) $this->encodePrimaryKey($bank_transaction->bank_transaction_rule_id) ?: '',
            'is_deleted' => (bool) $bank_transaction->is_deleted,
            'created_at' => (int) $bank_transaction->created_at,
            'updated_at' => (int) $bank_transaction->updated_at,
            'archived_at' => (int) $bank_transaction->deleted_at,
        ];
    }

    public function includeCompany(BankTransaction $bank_transaction)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($bank_transaction->company, $transformer, Company::class);
    }

    // public function includeExpense(BankTransaction $bank_transaction)
    // {
    //     $transformer = new ExpenseTransformer($this->serializer);

    //     return $this->includeItem($bank_transaction->expense, $transformer, Expense::class);
    // }

    public function includeVendor(BankTransaction $bank_transaction)
    {
        $transformer = new VendorTransformer($this->serializer);

        return $this->includeItem($bank_transaction->vendor, $transformer, Vendor::class);
    }

    public function includePayment(BankTransaction $bank_transaction)
    {
        $transformer = new PaymentTransformer($this->serializer);

        return $this->includeItem($bank_transaction->payment, $transformer, Payment::class);
    }
}
