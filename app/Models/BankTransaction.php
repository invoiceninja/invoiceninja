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

namespace App\Models;

use App\Services\Bank\BankService;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\BankTransaction
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int $bank_integration_id
 * @property int $transaction_id
 * @property string $amount
 * @property string|null $currency_code
 * @property int|null $currency_id
 * @property string|null $account_type
 * @property int|null $category_id
 * @property int|null $ninja_category_id
 * @property string $category_type
 * @property string $base_type
 * @property string|null $date
 * @property int $bank_account_id
 * @property string|null $description
 * @property string $invoice_ids
 * @property int|null $expense_id
 * @property int|null $vendor_id
 * @property int $status_id
 * @property int $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int|null $bank_transaction_rule_id
 * @property int|null $payment_id
 * @property-read \App\Models\BankIntegration $bank_integration
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Expense|null $expense
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\BankTransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereBankAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereBankIntegrationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereBankTransactionRuleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereBaseType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCategoryType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCurrencyCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereInvoiceIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereNinjaCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction wherePaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransaction withoutTrashed()
 * @mixin \Eloquent
 */
class BankTransaction extends BaseModel
{
    use SoftDeletes;
    use MakesHash;
    use Filterable;
    
    const STATUS_UNMATCHED = 1;

    const STATUS_MATCHED = 2;

    const STATUS_CONVERTED = 3;

    protected $fillable = [
        'currency_id',
        'category_id',
        'ninja_category_id',
        'date',
        'description',
        'base_type',
        'expense_id',
        'vendor_id',
        'amount'
    ];

    protected $dates = [
    ];
    
    public function getInvoiceIds()
    {
        $collection = collect();

        $invoices = explode(",", $this->invoice_ids);

        if (count($invoices) >= 1) {
            foreach ($invoices as $invoice) {
                if (is_string($invoice) && strlen($invoice) > 1) {
                    $collection->push($this->decodePrimaryKey($invoice));
                }
            }
        }

        return $collection;
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function bank_integration()
    {
        return $this->belongsTo(BankIntegration::class)->withTrashed();
    }

    public function account()
    {
        return $this->belongsTo(Account::class)->withTrashed();
    }

    public function service() :BankService
    {
        return new BankService($this);
    }
}
