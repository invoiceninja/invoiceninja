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

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Expense
 *
 * @property int $id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property int $company_id
 * @property int|null $vendor_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int|null $invoice_id
 * @property int|null $client_id
 * @property int|null $bank_id
 * @property int|null $invoice_currency_id
 * @property int|null $currency_id
 * @property int|null $category_id
 * @property int|null $payment_type_id
 * @property int|null $recurring_expense_id
 * @property bool $is_deleted
 * @property string $amount
 * @property string $foreign_amount
 * @property string $exchange_rate
 * @property string|null $tax_name1
 * @property string $tax_rate1
 * @property string|null $tax_name2
 * @property string $tax_rate2
 * @property string|null $tax_name3
 * @property string $tax_rate3
 * @property string|null $date
 * @property string|null $payment_date
 * @property string|null $private_notes
 * @property string|null $public_notes
 * @property string|null $transaction_reference
 * @property int $should_be_invoiced
 * @property int $invoice_documents
 * @property int|null $transaction_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $number
 * @property int|null $project_id
 * @property string $tax_amount1
 * @property string $tax_amount2
 * @property string $tax_amount3
 * @property int $uses_inclusive_taxes
 * @property int $calculate_tax_by_amount
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\ExpenseCategory|null $category
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Currency|null $currency
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\PaymentType|null $payment_type
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\PurchaseOrder|null $purchase_order
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\ExpenseFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Expense filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCalculateTaxByAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereForeignAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereInvoiceCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereInvoiceDocuments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense wherePaymentTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense wherePrivateNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense wherePublicNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereRecurringExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereShouldBeInvoiced($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxAmount1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxAmount2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxAmount3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxName1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxName2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxName3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxRate1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxRate2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTaxRate3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereUsesInclusiveTaxes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Expense withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Expense withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @mixin \Eloquent
 */
class Expense extends BaseModel
{
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'client_id',
        'assigned_user_id',
        'vendor_id',
        'invoice_id',
        'currency_id',
        'date',
        'invoice_currency_id',
        'amount',
        'foreign_amount',
        'exchange_rate',
        'private_notes',
        'public_notes',
        'bank_id',
        'transaction_id',
        'category_id',
        'tax_rate1',
        'tax_name1',
        'tax_rate2',
        'tax_name2',
        'tax_rate3',
        'tax_name3',
        'payment_date',
        'payment_type_id',
        'project_id',
        'transaction_reference',
        'invoice_documents',
        'should_be_invoiced',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'number',
        'tax_amount1',
        'tax_amount2',
        'tax_amount3',
        'uses_inclusive_taxes',
        'calculate_tax_by_amount',
        'purchase_order_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function purchase_order()
    {
        return $this->hasOne(PurchaseOrder::class);
    }

    public function translate_entity()
    {
        return ctrans('texts.expense');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function category()
    {
        return $this->belongsTo(ExpenseCategory::class)->withTrashed();
    }

    public function payment_type()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
