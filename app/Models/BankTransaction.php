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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransaction extends BaseModel
{
    use SoftDeletes;
    use MakesHash;

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

        if(count($invoices) >= 1) 
        {

            foreach($invoices as $invoice){

                if(is_string($invoice) && strlen($invoice) > 1)
                    $collection->push($this->decodePrimaryKey($invoice));
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

}