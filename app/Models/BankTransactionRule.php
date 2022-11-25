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

use App\Models\Filterable;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankTransactionRule extends BaseModel
{
    use SoftDeletes;
    use MakesHash;
    use Filterable;
    
    protected $fillable = [
        'name',
        'rules',
        'auto_convert',
        'matches_on_all',
        'applies_to',
        'client_id',
        'vendor_id',
        'category_id',
    ];

    protected $casts = [
        'rules' => 'array',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $dates = [
    ];
    
    protected array $search_keys = [
        'description' => 'string',
        'amount' => 'number',
    ];

    /* Amount */
    protected array $number_operators = [
        '=',
        '>',
        '>=',
        '<',
        '<='
    ];

    /* Description, Client, Vendor, Reference Number */
    protected array $string_operators = [
        'is',
        'contains',
        'starts_with',
        'is_empty',
    ];

    private array $search_results = [];

    // rule object looks like this:
    //[
    // {
    //     'search_key': 'client_id',
    //     'operator' : 'is',
    //     'value' : 'Sparky'
    // }
    //]

    // public function processRule(BankTransaction $bank_transaction)
    // {
    //     foreach($this->rules as $key => $rule)
    //     {
    //         $this->search($rule, $key, $bank_transaction);
    //     }
    // }

    // private function search($rule, $key, $bank_transaction)
    // {
    //     if($rule->search_key == 'amount')
    //     {
    //         //number search
    //     }
    //     else {
    //         //string search
    //     }
    // }

    // private function findAmount($amount, $bank_transaction)
    // {
    //     if($bank_transaction->base_type == 'CREDIT'){
    //         //search invoices
    //     }
    //     else{
    //         //search expenses
    //     }

    // }

    // private function searchClient($rule, $bank_transaction)
    // {
    //     if($bank_transaction->base_type == 'CREDIT'){
    //         //search invoices
    //     }
    //     else{
    //         //search expenses
    //     }

    // }

    // private function searchVendor($rule, $bank_transaction)
    // {
    //     //search expenses
        

    // }

    // private function searchDescription($rule, $bank_transaction)
    // {
    //     //search expenses public notes
    // }

    // private function searchReference($rule, $bank_transaction)
    // {
    //     if($bank_transaction->base_type == 'CREDIT'){
    //         //search invoices
    //     }
    //     else{
    //         //search expenses
    //     }
    // }

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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function expense_cateogry()
    {
        return $this->belongsTo(ExpenseCategory::class)->withTrashed();
    }

}