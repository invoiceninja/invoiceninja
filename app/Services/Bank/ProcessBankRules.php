<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Bank;

use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
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

    // $payment.amount => "Payment Amount", float
    // $payment.transaction_reference => "Payment Transaction Reference", string
    // $invoice.amount => "Invoice Amount", float
    // $invoice.number => "Invoice Number", string
    // $client.id_number => "Client ID Number", string
    // $client.email => "Client Email", string
    // $invoice.po_number => "Invoice Purchase Order Number", string
    private function matchCredit()
    {
        $this->invoices = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
                                ->whereIn('status_id', [1,2,3])
                                ->where('is_deleted', 0)
                                ->get();

        $invoice = $this->invoices->first(function ($value, $key) {
            return str_contains($this->bank_transaction->description, $value->number) || str_contains(str_replace("\n", "", $this->bank_transaction->description), $value->number);
        });

        if ($invoice) {
            $this->bank_transaction->invoice_ids = $invoice->hashed_id;
            $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
            $this->bank_transaction->save();
            return;
        }

        $this->credit_rules = $this->bank_transaction->company->credit_rules();

        //stub for credit rules
        foreach ($this->credit_rules as $bank_transaction_rule) {
        $matches = 0;

            if (!is_array($bank_transaction_rule['rules'])) {
                continue;
            }

            foreach ($bank_transaction_rule['rules'] as $rule) {
                $rule_count = count($bank_transaction_rule['rules']);

                $invoiceNumbers = false;
                $invoiceNumber = false;
                $invoiceAmounts = false;
                $paymentAmounts = false;
                $paymentReferences = false;
                $clientIdNumbers = false;
                $clientEmails = false;
                $invoicePONumbers = false;

                if ($rule['search_key'] == '$invoice.number') {

                    $invoiceNumbers = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
                                            ->whereIn('status_id', [1,2,3])
                                            ->where('is_deleted', 0)
                                            ->get();

                    $invoiceNumber = $invoiceNumbers->first(function ($value, $key) {
                        return str_contains($this->bank_transaction->description, $value->number) || str_contains(str_replace("\n", "", $this->bank_transaction->description), $value->number);
                    });

                    if($invoiceNumber)
                        $matches++;

                }

                if ($rule['search_key'] == '$invoice.po_number') {

                    $invoicePONumbers = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
                                            ->whereIn('status_id', [1,2,3])
                                            ->where('is_deleted', 0)
                                            ->where('po_number', $this->bank_transaction->description)
                                            ->get();

                    if($invoicePONumbers->count() > 0) {
                        $matches++;
                    }

                }

                if ($rule['search_key'] == '$invoice.amount') {

                    $$invoiceAmounts = Invoice::query()->where('company_id', $this->bank_transaction->company_id)
                                                                ->whereIn('status_id', [1,2,3])
                                                                ->where('is_deleted', 0)
                                                                ->where('amount', $rule['operator'], $this->bank_transaction->amount)
                                                                ->get();

                    $invoiceAmounts = $this->invoices;

                    if($invoiceAmounts->count() > 0) {
                        $matches++;
                    }

                }

                if ($rule['search_key'] == '$payment.amount') {


                    $paymentAmounts = Payment::query()->where('company_id', $this->bank_transaction->company_id)
                                            ->whereIn('status_id', [1,4])
                                            ->where('is_deleted', 0)
                                            ->whereNull('transaction_id')
                                            ->where('amount', $rule['operator'], $this->bank_transaction->amount)
                                            ->get();

                    

                    if($paymentAmounts->count() > 0) {
                        $matches++;
                    }

                }


                if ($rule['search_key'] == '$payment.transaction_reference') {

                    $ref_search = $this->bank_transaction->description;

                    switch ($rule['operator']) {
                        case 'is':
                            $operator = '=';
                            break;
                        case 'contains':
                            $ref_search = "%".$ref_search."%";
                            $operator = 'LIKE';
                            break;

                        default:
                            $operator = '=';
                            break;
                    }

                    $paymentReferences = Payment::query()->where('company_id', $this->bank_transaction->company_id)
                                            ->whereIn('status_id', [1,4])
                                            ->where('is_deleted', 0)
                                            ->whereNull('transaction_id')
                                            ->where('transaction_reference', $operator, $ref_search)
                                            ->get();



                    if($paymentReferences->count() > 0) {
                        $matches++;
                    }

                }

                if ($rule['search_key'] == '$client.id_number') {
                    
                    $ref_search = $this->bank_transaction->description;

                    switch ($rule['operator']) {
                        case 'is':
                            $operator = '=';
                            break;
                        case 'contains':
                            $ref_search = "%".$ref_search."%";
                            $operator = 'LIKE';
                            break;

                        default:
                            $operator = '=';
                            break;
                    }

                    $clientIdNumbers = Client::query()->where('company_id', $this->bank_transaction->company_id)
                                        ->where('id_number', $operator, $ref_search)
                                        ->get();

                    if($clientIdNumbers->count() > 0) {
                        $matches++;
                    }

                }

                
                if ($rule['search_key'] == '$client.email') {

                    $clientEmails = Client::query()
                                        ->where('company_id', $this->bank_transaction->company_id)
                                        ->whereHas('contacts', function ($q){
                                            $q->where('email', $this->bank_transaction->description);
                                        })
                                        ->get();


                    if($clientEmails->count() > 0) {
                        $matches++;
                    }

                    if (($bank_transaction_rule['matches_on_all'] && ($matches == $rule_count)) || (!$bank_transaction_rule['matches_on_all'] && $matches > 0)) {

                        //determine which combination has succeeded, ie link a payment / or / invoice
                        $invoice_ids = null;
                        $payment_id = null;

                        if($invoiceNumber){
                            $invoice_ids = $invoiceNumber->hashed_id;
                        }
                        
                        if($invoicePONumbers && strlen($invoice_ids ?? '') == 0){

                            if($clientEmails){ // @phpstan-ignore-line

                                $invoice_ids = $this->matchInvoiceAndClient($invoicePONumbers, $clientEmails);

                            }
                            
                            if($clientIdNumbers && strlen($invoice_ids ?? '') == 0)
                            {

                                $invoice_ids = $this->matchInvoiceAndClient($invoicePONumbers, $clientIdNumbers);

                            }

                            if(strlen($invoice_ids ?? '') == 0)
                            {
                                $invoice_ids = $invoicePONumbers->first()->hashed_id;
                            }

                        }


                        if($invoiceAmounts && strlen($invoice_ids ?? '') == 0) {

                            if($clientEmails) {// @phpstan-ignore-line

                                $invoice_ids = $this->matchInvoiceAndClient($invoiceAmounts, $clientEmails);

                            }

                            if($clientIdNumbers && strlen($invoice_ids ?? '') == 0) {

                                $invoice_ids = $this->matchInvoiceAndClient($invoiceAmounts, $clientIdNumbers);

                            }

                            if(strlen($invoice_ids ?? '') == 0) {
                                $invoice_ids = $invoiceAmounts->first()->hashed_id;
                            }

                        }


                        if($paymentAmounts && strlen($invoice_ids ?? '') == 0 && is_null($payment_id)) {

                            if($clientEmails) {// @phpstan-ignore-line

                                $payment_id = $this->matchPaymentAndClient($paymentAmounts, $clientEmails);

                            }

                            if($clientIdNumbers && is_null($payment_id)) {


                                $payment_id = $this->matchPaymentAndClient($paymentAmounts, $clientEmails);

                            }

                            if(is_null($payment_id)) {
                                $payment_id = $paymentAmounts->first()->id;
                            }

                        }

                        if(strlen($invoice_ids ?? '') > 1 || is_int($payment_id))
                        {

                            $this->bank_transaction->payment_id = $payment_id;
                            $this->bank_transaction->invoice_ids = $invoice_ids;
                            $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
                            $this->bank_transaction->bank_transaction_rule_id = $bank_transaction_rule->id;
                            $this->bank_transaction->save();
                        
                        }

                    }

                }

            }

        }

    }


    private function matchPaymentAndClient($payments, $clients): ?int
    {
        /** @var \Illuminate\Support\Collection<Payment> $payments */
        foreach($payments as $payment) {
            foreach($clients as $client) {

                if($payment->client_id == $client->id) {
                    return $payment->id;
                    
                }
            }
        }

        return null;
    }

    private function matchInvoiceAndClient($invoices, $clients): ?Invoice
    {
        /** @var \Illuminate\Support\Collection<Invoice> $invoices */
        foreach($invoices as $invoice) {
            foreach($clients as $client) {

                if($invoice->client_id == $client->id) {
                    return $invoice->hashed_id;
                    
                }
            }
        }

        return null;
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

        if (!$this->bank_transaction->expense_id || strlen($this->bank_transaction->expense_id ?? '') < 2) {
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
