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

namespace App\Services\Bank;

use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Libraries\MultiDB;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class BankMatchingService implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, GeneratesCounter;

    private $company_id;

    private Company $company;

    private $db;

    private $invoices;

    public $deleteWhenMissingModels = true;

    protected $credit_rules;

    protected $debit_rules;

    protected $categories;

    public function __construct($company_id, $db)
    {
        $this->company_id = $company_id;
        $this->db = $db;
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->company_key)];
    }

    public function handle()
    {

        MultiDB::setDb($this->db);

        $this->company = Company::find($this->company_id);

        $this->categories = collect(Cache::get('bank_categories'));
        
        $this->matchCredits();
    
        $this->matchDebits();

    }

    private function matchDebits()
    {
        
        $this->debit_rules = $this->company->debit_rules();

        BankTransaction::where('company_id', $this->company->id)
           ->where('status_id', BankTransaction::STATUS_UNMATCHED)
           ->where('base_type', 'DEBIT')
           ->cursor()
           ->each(function ($bt){
            
               $this->matchDebit($bt);

           });

    }

    private function matchDebit(BankTransaction $bank_transaction)
    {
        $matches = 0;

        foreach($this->debit_rules as $rule)
        {
            $rule_count = count($this->debit_rules);

            if($rule['search_key'] == 'description')
            {

                if($this->matchStringOperator($bank_transaction->description, 'description', $rule['operator'])){
                    $matches++;
                }

            }

            if($rule['search_key'] == 'amount')
            {

                if($this->matchNumberOperator($bank_transaction->description, 'amount', $rule['operator'])){
                    $matches++;
                }

            }

            if(($rule['matches_on_all'] && ($matches == $rule_count)) || (!$rule['matches_on_all'] &&$matches > 0))
            {

                $bank_transaction->client_id = empty($rule['client_id']) ? null : $rule['client_id'];
                $bank_transaction->vendor_id = empty($rule['vendor_id']) ? null : $rule['vendor_id'];
                $bank_transaction->ninja_category_id = empty($rule['category_id']) ? null : $rule['category_id'];
                $bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
                $bank_transaction->save();

                if($rule['auto_convert'])
                {

                    $expense = ExpenseFactory::create($bank_transaction->company_id, $bank_transaction->user_id);
                    $expense->category_id = $bank_transaction->ninja_category_id ?: $this->resolveCategory($bank_transaction);
                    $expense->amount = $bank_transaction->amount;
                    $expense->number = $this->getNextExpenseNumber($expense);
                    $expense->currency_id = $bank_transaction->currency_id;
                    $expense->date = Carbon::parse($bank_transaction->date);
                    $expense->payment_date = Carbon::parse($bank_transaction->date);
                    $expense->transaction_reference = $bank_transaction->description;
                    $expense->transaction_id = $bank_transaction->id;
                    $expense->vendor_id = $bank_transaction->vendor_id;
                    $expense->invoice_documents = $this->company->invoice_expense_documents;
                    $expense->should_be_invoiced = $this->company->mark_expenses_invoiceable;
                    $expense->save();

                    $bank_transaction->expense_id = $expense->id;
                    $bank_transaction->status_id = BankTransaction::STATUS_CONVERTED;
                    $bank_transaction->save();

                    break;
                    
                }

            }

        }

    }

    private function resolveCategory(BankTransaction $bank_transaction)
    {
        $category = $this->categories->firstWhere('highLevelCategoryId', $bank_transaction->category_id);

        $ec = ExpenseCategory::where('company_id', $this->company->id)->where('bank_category_id', $bank_transaction->category_id)->first();

        if($ec)
            return $ec->id;

        if($category)
        {
            $ec = ExpenseCategoryFactory::create($bank_transaction->company_id, $bank_transaction->user_id);
            $ec->bank_category_id = $bank_transaction->category_id;
            $ec->name = $category->highLevelCategoryName;
            $ec->save();

            return $ec->id;
        }
    }

    private function matchNumberOperator($bt_value, $rule_value, $operator) :bool
    {

        return match ($operator) {
            '>' => floatval($bt_value) > floatval($rule_value),
            '>=' => floatval($bt_value) >= floatval($rule_value),
            '=' => floatval($bt_value) == floatval($rule_value),
            '<' => floatval($bt_value) < floatval($rule_value),
            '<=' => floatval($bt_value) <= floatval($rule_value),
            default => false,
        };

    }

    private function matchStringOperator($bt_value, $rule_value, $operator) :bool
    {
        $bt_value = strtolower(str_replace(" ", "", $bt_value));
        $rule_value = strtolower(str_replace(" ", "", $rule_value));
        $rule_length = iconv_strlen($rule_value);

        return match ($operator) {
            'is' =>  $bt_value == $rule_value,
            'contains' => str_contains($bt_value, $rule_value),
            'starts_with' => substr($bt_value, 0, $rule_length) == $rule_value,
            'is_empty' => empty($bt_value),
            default => false,
        };

    }



    /* Currently we don't consider rules here, only matching on invoice number*/
    private function matchCredits()
    {
        $this->credit_rules = $this->company->credit_rules();

        $this->invoices = Invoice::where('company_id', $this->company->id)
                                ->whereIn('status_id', [1,2,3])
                                ->where('is_deleted', 0)
                                ->get();

        BankTransaction::where('company_id', $this->company->id)
                       ->where('status_id', BankTransaction::STATUS_UNMATCHED)
                       ->where('base_type', 'CREDIT')
                       ->cursor()
                       ->each(function ($bt){
                        
                            $invoice = $this->invoices->first(function ($value, $key) use ($bt){

                                    return str_contains($bt->description, $value->number);
                                    
                                });

                            if($invoice)
                            {
                                $bt->invoice_ids = $invoice->hashed_id;
                                $bt->status_id = BankTransaction::STATUS_MATCHED;
                                $bt->save();   
                            }

                       });
    }


}
