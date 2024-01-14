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
 * App\Models\BankTransactionRule
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property string $name
 * @property array|null $rules
 * @property int $auto_convert
 * @property int $matches_on_all
 * @property string $applies_to
 * @property int|null $client_id
 * @property int|null $vendor_id
 * @property int|null $category_id
 * @property int $is_deleted
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\ExpenseCategory|null $expense_category
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\BankTransactionRuleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereAppliesTo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereAutoConvert($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereMatchesOnAll($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereRules($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule whereVendorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BankTransactionRule withoutTrashed()
 * @mixin \Eloquent
 */
class BankTransactionRule extends BaseModel
{
    use SoftDeletes;
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

    public function expense_category()
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id')->withTrashed();
    }


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
}
