<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Bank;

use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Jobs\Bank\MatchBankTransactions;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ProcessBankRules extends AbstractService
{
    use GeneratesCounter;
    use MakesHash;

    protected $credit_rules;

    protected $debit_rules;

    protected $categories;

    protected $invoices;

    /**
     * @param \App\Models\BankTransaction $bank_transaction
     */
    public function __construct(public BankTransaction $bank_transaction) {}

    public function run()
    {
        if ($this->bank_transaction->base_type == 'DEBIT') {
            $this->matchDebit();
        } else {

            if ($this->bank_transaction->description) {
                $this->matchCredit();
            }

        }
    }

    private function matchCredit()
    {
        // First try simple invoice number match in description
        $this->invoices = Invoice::query()
                                ->withTrashed()
                                ->where('company_id', $this->bank_transaction->company_id)
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

        // Process credit rules
        $this->credit_rules = $this->bank_transaction->company->credit_rules();

        foreach ($this->credit_rules as $bank_transaction_rule) {
            if (!is_array($bank_transaction_rule['rules'])) {
                continue;
            }

            $rule_count = count($bank_transaction_rule['rules']);
            $matches = 0;

            // Initialize result storage
            $invoiceNumber = null;
            $invoicePONumbers = null;
            $invoiceAmounts = null;
            $invoiceCustomMatches = [];
            $paymentAmounts = null;
            $paymentReferences = null;
            $paymentCustomMatches = [];
            $clientIdNumbers = null;
            $clientEmails = null;
            $clientCustomMatches = [];

            foreach ($bank_transaction_rule['rules'] as $rule) {
                $matched = false;

                // Use match expression to handle each search key
                match($rule['search_key']) {
                    '$invoice.number' => $matched = $this->searchInvoiceNumber($invoiceNumber),
                    '$invoice.po_number' => $matched = $this->searchInvoicePONumber($invoicePONumbers, $rule),
                    '$invoice.amount' => $matched = $this->searchInvoiceAmount($invoiceAmounts, $rule),
                    '$invoice.custom1' => $matched = $this->searchInvoiceCustomField($invoiceCustomMatches, 'custom_value1', $rule),
                    '$invoice.custom2' => $matched = $this->searchInvoiceCustomField($invoiceCustomMatches, 'custom_value2', $rule),
                    '$invoice.custom3' => $matched = $this->searchInvoiceCustomField($invoiceCustomMatches, 'custom_value3', $rule),
                    '$invoice.custom4' => $matched = $this->searchInvoiceCustomField($invoiceCustomMatches, 'custom_value4', $rule),
                    '$payment.amount' => $matched = $this->searchPaymentAmount($paymentAmounts, $rule),
                    '$payment.transaction_reference' => $matched = $this->searchPaymentReference($paymentReferences, $rule),
                    '$payment.custom1' => $matched = $this->searchPaymentCustomField($paymentCustomMatches, 'custom_value1', $rule),
                    '$payment.custom2' => $matched = $this->searchPaymentCustomField($paymentCustomMatches, 'custom_value2', $rule),
                    '$payment.custom3' => $matched = $this->searchPaymentCustomField($paymentCustomMatches, 'custom_value3', $rule),
                    '$payment.custom4' => $matched = $this->searchPaymentCustomField($paymentCustomMatches, 'custom_value4', $rule),
                    '$client.id_number' => $matched = $this->searchClientIdNumber($clientIdNumbers, $rule),
                    '$client.email' => $matched = $this->searchClientEmail($clientEmails),
                    '$client.custom1' => $matched = $this->searchClientCustomField($clientCustomMatches, 'custom_value1', $rule),
                    '$client.custom2' => $matched = $this->searchClientCustomField($clientCustomMatches, 'custom_value2', $rule),
                    '$client.custom3' => $matched = $this->searchClientCustomField($clientCustomMatches, 'custom_value3', $rule),
                    '$client.custom4' => $matched = $this->searchClientCustomField($clientCustomMatches, 'custom_value4', $rule),
                    default => $matched = false,
                };

                if ($matched) {
                    $matches++;
                }
            }

            // Check if rule criteria met - NOW OUTSIDE THE INNER FOREACH LOOP
            if (($bank_transaction_rule['matches_on_all'] && ($matches == $rule_count)) ||
                (!$bank_transaction_rule['matches_on_all'] && $matches > 0)) {

                // Determine which combination succeeded and link payment/invoice
                $invoice_ids = null;
                $payment_id = null;

                // Priority 1: Direct invoice number match
                if ($invoiceNumber) {
                    $invoice_ids = $invoiceNumber->hashed_id;
                }

                // Priority 2: PO Number matches
                if (!$invoice_ids && $invoicePONumbers && $invoicePONumbers->count() > 0) {
                    if ($clientEmails && $clientEmails->count() > 0) {
                        $invoice_ids = $this->matchInvoiceAndClient($invoicePONumbers, $clientEmails);
                    }

                    if (!$invoice_ids && $clientIdNumbers && $clientIdNumbers->count() > 0) {
                        $invoice_ids = $this->matchInvoiceAndClient($invoicePONumbers, $clientIdNumbers);
                    }

                    if (!$invoice_ids) {
                        $invoice_ids = $invoicePONumbers->first()->hashed_id;
                    }
                }

                // Priority 3: Amount matches
                if (!$invoice_ids && $invoiceAmounts && $invoiceAmounts->count() > 0) {
                    if ($clientEmails && $clientEmails->count() > 0) {
                        $invoice_ids = $this->matchInvoiceAndClient($invoiceAmounts, $clientEmails);
                    }

                    if (!$invoice_ids && $clientIdNumbers && $clientIdNumbers->count() > 0) {
                        $invoice_ids = $this->matchInvoiceAndClient($invoiceAmounts, $clientIdNumbers);
                    }

                    if (!$invoice_ids) {
                        $invoice_ids = $invoiceAmounts->first()->hashed_id;
                    }
                }

                // Priority 4: Custom field matches
                if (!$invoice_ids && count($invoiceCustomMatches) > 0) {
                    $customInvoices = collect($invoiceCustomMatches)->flatten()->filter();
                    if ($customInvoices->count() > 0) {
                        if ($clientEmails && $clientEmails->count() > 0) {
                            $invoice_ids = $this->matchInvoiceAndClient($customInvoices, $clientEmails);
                        }

                        if (!$invoice_ids && $clientIdNumbers && $clientIdNumbers->count() > 0) {
                            $invoice_ids = $this->matchInvoiceAndClient($customInvoices, $clientIdNumbers);
                        }

                        if (!$invoice_ids) {
                            $invoice_ids = $customInvoices->first()->hashed_id;
                        }
                    }
                }

                // Priority 5: Payment amount matches
                if (!$invoice_ids && !$payment_id && $paymentAmounts && $paymentAmounts->count() > 0) {
                    if ($clientEmails && $clientEmails->count() > 0) {
                        $payment_id = $this->matchPaymentAndClient($paymentAmounts, $clientEmails);
                    }

                    if (!$payment_id && $clientIdNumbers && $clientIdNumbers->count() > 0) {
                        $payment_id = $this->matchPaymentAndClient($paymentAmounts, $clientIdNumbers);
                    }

                    if (!$payment_id) {
                        $payment_id = $paymentAmounts->first()->id;
                    }
                }

                // Priority 6: Payment reference matches
                if (!$invoice_ids && !$payment_id && $paymentReferences && $paymentReferences->count() > 0) {
                    if ($clientEmails && $clientEmails->count() > 0) {
                        $payment_id = $this->matchPaymentAndClient($paymentReferences, $clientEmails);
                    }

                    if (!$payment_id && $clientIdNumbers && $clientIdNumbers->count() > 0) {
                        $payment_id = $this->matchPaymentAndClient($paymentReferences, $clientIdNumbers);
                    }

                    if (!$payment_id) {
                        $payment_id = $paymentReferences->first()->id;
                    }
                }

                // Priority 7: Payment custom field matches
                if (!$invoice_ids && !$payment_id && count($paymentCustomMatches) > 0) {
                    $customPayments = collect($paymentCustomMatches)->flatten()->filter();
                    if ($customPayments->count() > 0) {
                        if ($clientEmails && $clientEmails->count() > 0) {
                            $payment_id = $this->matchPaymentAndClient($customPayments, $clientEmails);
                        }

                        if (!$payment_id && $clientIdNumbers && $clientIdNumbers->count() > 0) {
                            $payment_id = $this->matchPaymentAndClient($customPayments, $clientIdNumbers);
                        }

                        if (!$payment_id) {
                            $payment_id = $customPayments->first()->id;
                        }
                    }
                }

                // Save the match if we found something
                if ($invoice_ids || $payment_id) {
                    $this->bank_transaction->payment_id = $payment_id;
                    $this->bank_transaction->invoice_ids = $invoice_ids;
                    $this->bank_transaction->status_id = BankTransaction::STATUS_MATCHED;
                    $this->bank_transaction->bank_transaction_rule_id = $bank_transaction_rule->id;
                    $this->bank_transaction->save();

                    if ($bank_transaction_rule['auto_convert']) {
                        (new MatchBankTransactions($this->bank_transaction->company->id, $this->bank_transaction->company->db, [
                            'transactions' => [
                                [
                                    'id' => $this->bank_transaction->id,
                                    'invoice_ids' => $invoice_ids ?? '',
                                    'payment_id' => $payment_id ?? '',
                                ],
                            ],
                        ]))->handle();
                    }

                    break; // Stop after first successful match
                }
            }
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
                    if ($this->matchStringOperator($this->bank_transaction->description ?? '', $rule['value'] ?? '', $rule['operator'] ?? '')) {
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
        $bt_value = (float) $bt_value;
        $rule_value = (float) $rule_value;

        return match ($operator) {
            '>'  => round($bt_value - $rule_value, 2) > 0,
            '>=' => round($bt_value - $rule_value, 2) >= 0,
            '='  => round($bt_value - $rule_value, 2) == 0,
            '<'  => round($bt_value - $rule_value, 2) < 0,
            '<=' => round($bt_value - $rule_value, 2) <= 0,
            default => false,
        };
    }

    private function matchStringOperator($bt_value, $rule_value, $operator): bool
    {
        $bt_value = strtolower(str_replace(" ", "", $bt_value ?? ''));
        $rule_value = strtolower(str_replace(" ", "", $rule_value ?? ''));
        $rule_length = iconv_strlen($rule_value);
        // nlog($bt_value);
        // nlog($rule_value);
        // nlog($rule_length);
        return match ($operator) {
            'is' =>  $bt_value == $rule_value,
            'contains' => stripos($bt_value, $rule_value) !== false && strlen($rule_value) > 1,
            'starts_with' => substr($bt_value, 0, $rule_length) == $rule_value && strlen($rule_value) > 1,
            'is_empty' => empty($bt_value),
            default => false,
        };
    }

    private function searchInvoiceNumber(&$invoiceNumber): bool
    {
        if ($invoiceNumber !== null) {
            return $invoiceNumber !== false;
        }

        $invoices = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereIn('status_id', [1,2,3])
            ->where('is_deleted', 0)
            ->get();

        $invoiceNumber = $invoices->first(function ($value) {
            $description = str_replace("\n", "", $this->bank_transaction->description);
            return str_contains($description, $value->number);
        });

        return $invoiceNumber !== null;
    }

    private function searchInvoicePONumber(&$invoicePONumbers, array $rule): bool
    {
        if ($invoicePONumbers !== null) {
            return $invoicePONumbers->count() > 0;
        }

        $invoicePONumbers = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereIn('status_id', [1,2,3])
            ->where('is_deleted', 0)
            ->get()
            ->filter(function ($invoice) use ($rule) {
                return $this->matchStringOperator(
                    $this->bank_transaction->description,
                    $invoice->po_number,
                    $rule['operator']
                );
            });

        return $invoicePONumbers->count() > 0;
    }

    private function searchInvoiceAmount(&$invoiceAmounts, array $rule): bool
    {
        if ($invoiceAmounts !== null) {
            return $invoiceAmounts->count() > 0;
        }

        $invoiceAmounts = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereIn('status_id', [1,2,3])
            ->where('is_deleted', 0)
            ->get()
            ->filter(function ($invoice) use ($rule) {
                return $this->matchNumberOperator(
                    $this->bank_transaction->amount,
                    $invoice->amount,
                    $rule['operator']
                );
            });

        return $invoiceAmounts->count() > 0;
    }

    private function searchInvoiceCustomField(&$invoiceCustomMatches, string $field, array $rule): bool
    {
        if (isset($invoiceCustomMatches[$field])) {
            return $invoiceCustomMatches[$field]->count() > 0;
        }

        $invoiceCustomMatches[$field] = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereIn('status_id', [1,2,3])
            ->where('is_deleted', 0)
            ->get()
            ->filter(function ($invoice) use ($rule, $field) {
                return $this->matchStringOperator(
                    $this->bank_transaction->description,
                    $invoice->{$field},
                    $rule['operator']
                );
            });

        return $invoiceCustomMatches[$field]->count() > 0;
    }

    private function searchPaymentAmount(&$paymentAmounts, array $rule): bool
    {
        if ($paymentAmounts !== null) {
            return $paymentAmounts->count() > 0;
        }

        $paymentAmounts = Payment::query()
            ->withTrashed()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereIn('status_id', [1,4])
            ->where('is_deleted', 0)
            ->whereNull('transaction_id')
            ->get()
            ->filter(function ($payment) use ($rule) {
                return $this->matchNumberOperator(
                    $this->bank_transaction->amount,
                    $payment->amount,
                    $rule['operator']
                );
            });

        return $paymentAmounts->count() > 0;
    }

    private function searchPaymentReference(&$paymentReferences, array $rule): bool
    {
        if ($paymentReferences !== null) {
            return $paymentReferences->count() > 0;
        }

        $paymentReferences = Payment::query()
            ->withTrashed()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereIn('status_id', [1,4])
            ->where('is_deleted', 0)
            ->whereNull('transaction_id')
            ->get()
            ->filter(function ($payment) use ($rule) {
                return $this->matchStringOperator(
                    $this->bank_transaction->description,
                    $payment->transaction_reference,
                    $rule['operator']
                );
            });

        return $paymentReferences->count() > 0;
    }

    private function searchPaymentCustomField(&$paymentCustomMatches, string $field, array $rule): bool
    {
        if (isset($paymentCustomMatches[$field])) {
            return $paymentCustomMatches[$field]->count() > 0;
        }

        $paymentCustomMatches[$field] = Payment::query()
            ->withTrashed()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereIn('status_id', [1,4])
            ->where('is_deleted', 0)
            ->whereNull('transaction_id')
            ->get()
            ->filter(function ($payment) use ($rule, $field) {
                return $this->matchStringOperator(
                    $this->bank_transaction->description,
                    $payment->{$field},
                    $rule['operator']
                );
            });

        return $paymentCustomMatches[$field]->count() > 0;
    }

    private function searchClientIdNumber(&$clientIdNumbers, array $rule): bool
    {
        if ($clientIdNumbers !== null) {
            return $clientIdNumbers->count() > 0;
        }

        $clientIdNumbers = Client::query()
            ->where('company_id', $this->bank_transaction->company_id)
            ->get()
            ->filter(function ($client) use ($rule) {
                return $this->matchStringOperator(
                    $this->bank_transaction->description,
                    $client->id_number,
                    $rule['operator']
                );
            });

        return $clientIdNumbers->count() > 0;
    }

    private function searchClientEmail(&$clientEmails): bool
    {
        if ($clientEmails !== null) {
            return $clientEmails->count() > 0;
        }

        $clientEmails = Client::query()
            ->where('company_id', $this->bank_transaction->company_id)
            ->whereHas('contacts', function ($q) {
                $q->where('email', $this->bank_transaction->description);
            })
            ->get();

        return $clientEmails->count() > 0;
    }

    private function searchClientCustomField(&$clientCustomMatches, string $field, array $rule): bool
    {
        if (isset($clientCustomMatches[$field])) {
            return $clientCustomMatches[$field]->count() > 0;
        }

        $clientCustomMatches[$field] = Client::query()
            ->where('company_id', $this->bank_transaction->company_id)
            ->get()
            ->filter(function ($client) use ($rule, $field) {
                return $this->matchStringOperator(
                    $this->bank_transaction->description,
                    $client->{$field},
                    $rule['operator']
                );
            });

        return $clientCustomMatches[$field]->count() > 0;
    }

    private function matchPaymentAndClient($payments, $clients): ?int
    {
        foreach($payments as $payment) {
            foreach($clients as $client) {
                if($payment->client_id == $client->id) {
                    return $payment->id;
                }
            }
        }

        return null;
    }

    private function matchInvoiceAndClient($invoices, $clients): ?string
    {
        foreach($invoices as $invoice) {
            foreach($clients as $client) {
                if($invoice->client_id == $client->id) {
                    return $invoice->hashed_id;
                }
            }
        }

        return null;
    }
}
