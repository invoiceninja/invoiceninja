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

namespace App\Services\Bank;

use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Models\BankTransaction;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ProcessBankRules extends AbstractService
{
    use GeneratesCounter;

    protected $credit_rules;

    protected $debit_rules;

    protected $categories;

    protected $invoices;

    /**
     * @param \App\Models\BankTransaction $bank_transaction
     */
    public function __construct(public BankTransaction $bank_transaction)
    {
    }

    public function run()
    {
        if ($this->bank_transaction->base_type == 'DEBIT') {
            $this->matchDebit();
        } else {
            $this->matchCredit();
        }
    }

    private function matchCredit()
    {
        $this->invoices = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
                                ->whereIn('status_id', [1,2,3])
                                ->where('is_deleted', 0)
                                ->get();

        $invoice = $this->invoices->first(function ($value, $key) {
            return str_contains($this->bank_transaction->description, $value->number);
        });

        if ($invoice) {
            $this->bank_transaction->invoice_ids = $invoice->hashed_id;
            $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
            $this->bank_transaction->save();
            return;
        }

        $this->credit_rules = $this->bank_transaction->company->credit_rules();

        //stub for credit rules
        foreach ($this->credit_rules as $rule) {
            //   $this->bank_transaction->bank_transaction_rule_id = $bank_transaction_rule->id;
        }
    }

    private function matchDebit()
    {
        $this->debit_rules = $this->bank_transaction->company->debit_rules();

        $this->categories = collect(Cache::get('bank_categories'));



        foreach ($this->debit_rules as $bank_transaction_rule) {
            $matches = 0;

            if (!is_array($bank_transaction_rule['rules'])) {
                continue;
            }

            foreach ($bank_transaction_rule['rules'] as $rule) {
                $rule_count = count($bank_transaction_rule['rules']);

                if ($rule['search_key'] == 'description') {
                    if ($this->matchStringOperator($this->bank_transaction->description, $rule['value'], $rule['operator'])) {
                        $matches++;
                    }
                }

                if ($rule['search_key'] == 'amount') {
                    if ($this->matchNumberOperator($this->bank_transaction->amount, $rule['value'], $rule['operator'])) {
                        $matches++;
                    }
                }

                if (($bank_transaction_rule['matches_on_all'] && ($matches == $rule_count)) || (!$bank_transaction_rule['matches_on_all'] && $matches > 0)) {
                    // $this->bank_transaction->client_id = empty($rule['client_id']) ? null : $rule['client_id'];
                    $this->bank_transaction->vendor_id = $bank_transaction_rule->vendor_id;
                    $this->bank_transaction->ninja_category_id = $bank_transaction_rule->category_id;
                    $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
                    $this->bank_transaction->bank_transaction_rule_id = $bank_transaction_rule->id;
                    $this->bank_transaction->save();

                    if ($bank_transaction_rule['auto_convert']) {
                        $expense = ExpenseFactory::create($this->bank_transaction->company_id, $this->bank_transaction->user_id);
                        $expense->category_id = $bank_transaction_rule->category_id ?: $this->resolveCategory();
                        $expense->amount = $this->bank_transaction->amount;
                        $expense->number = $this->getNextExpenseNumber($expense);
                        $expense->currency_id = $this->bank_transaction->currency_id;
                        $expense->date = Carbon::parse($this->bank_transaction->date);
                        $expense->payment_date = Carbon::parse($this->bank_transaction->date);
                        $expense->transaction_reference = $this->bank_transaction->description;
                        $expense->transaction_id = $this->bank_transaction->id;
                        $expense->vendor_id = $bank_transaction_rule->vendor_id;
                        $expense->invoice_documents = $this->bank_transaction->company->invoice_expense_documents;
                        $expense->should_be_invoiced = $this->bank_transaction->company->mark_expenses_invoiceable;
                        $expense->save();

                        $this->bank_transaction->expense_id = $this->coalesceExpenses($expense->hashed_id);
                        $this->bank_transaction->status_id = BankTransaction::STATUS_CONVERTED;
                        $this->bank_transaction->save();

                        break;
                    }
                }
            }
        }
    }

    private function coalesceExpenses($expense): string
    {

        if (!$this->bank_transaction->expense_id || strlen($this->bank_transaction->expense_id) < 1) {
            return $expense;
        }

        return collect(explode(",", $this->bank_transaction->expense_id))->push($expense)->implode(",");

    }

    private function resolveCategory()
    {
        $category = $this->categories->firstWhere('highLevelCategoryId', $this->bank_transaction->category_id);

        $ec = ExpenseCategory::query()->where('company_id', $this->bank_transaction->company_id)->where('bank_category_id', $this->bank_transaction->category_id)->first();

        if ($ec) {
            return $ec->id;
        }

        if ($category) {
            $ec = ExpenseCategoryFactory::create($this->bank_transaction->company_id, $this->bank_transaction->user_id);
            $ec->bank_category_id = $this->bank_transaction->category_id;
            $ec->name = $category->highLevelCategoryName;
            $ec->save();

            return $ec->id;
        }
    }

    private function matchNumberOperator($bt_value, $rule_value, $operator): bool
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

    private function matchStringOperator($bt_value, $rule_value, $operator): bool
    {
        $bt_value = strtolower(str_replace(" ", "", $bt_value));
        $rule_value = strtolower(str_replace(" ", "", $rule_value));
        $rule_length = iconv_strlen($rule_value);

        return match ($operator) {
            'is' =>  $bt_value == $rule_value,
            'contains' => stripos($bt_value, $rule_value) !== false,
            'starts_with' => substr($bt_value, 0, $rule_length) == $rule_value,
            'is_empty' => empty($bt_value),
            default => false,
        };
    }
}
