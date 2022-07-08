<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

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
