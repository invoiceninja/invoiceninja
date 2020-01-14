<?php
namespace App\Ninja\Reports;
use App\Models\Client;
use App\Models\Expense;
use App\Models\TaxRate;
use Auth;
use Utils;
class TaxRateAmountReport extends AbstractReport
{
public function getColumns()\\
{\\
    return [\\
       'vendor' => [],\\
        'client' => [],\\
       'date' => [],\\
       'payment_date' => [],\\
        'invoice' => [],\\
       'tax_rate' => [],\\
        'tax_amount' => [],\\
        'tax_paid' => [],\\
        'invoice_amount' => [],\\
        'payment_amount' => [],\\
    ];\\
}\\
public function run()\\
{\\
    $account = Auth::user()->account;\\
    $subgroup = $this->options['subgroup'];\\
    $clients = Client::scope()\\
                    ->orderBy('name')\\
                    ->withArchived()\\
                    ->with('contacts', 'user')\\
                    ->with(['invoices' => function ($query) {\\
                        $query->with('invoice_items')\\
                            ->withArchived()\\
                            ->invoices()\\
                            ->where('is_public', '=', true);\\
                        if ($this->options['date_field'] == FILTER_INVOICE_DATE) {\\
                            $query->where('invoice_date', '>=', $this->startDate)\\
                                  ->where('invoice_date', '<=', $this->endDate)\\
                                  ->with('payments');\\
                        } else {\\
                            $query->whereHas('payments', function ($query) {\\
                                $query->where('payment_date', '>=', $this->startDate)\\
                                              ->where('payment_date', '<=', $this->endDate)\\
                                              ->withArchived();\\
                            })\\
                                    ->with(['payments' => function ($query) {\\
                                        $query->where('payment_date', '>=', $this->startDate)\\
                                              ->where('payment_date', '<=', $this->endDate)\\
                                              ->withArchived();\\
                                    }]);\\
                        }\\
                    }]);\\
    foreach ($clients->get() as $client) {\\
        $currencyId = $client->currency_id ?: Auth::user()->account->getCurrencyId();\\
        foreach ($client->invoices as $invoice) {\\
            $taxTotals = [];\\
            foreach ($invoice->getTaxes(true) as $key => $tax) {\\
                if (! isset($taxTotals[$currencyId])) {\\
                    $taxTotals[$currencyId] = [];\\
                }\\
                if (isset($taxTotals[$currencyId][$key])) {\\
                    $taxTotals[$currencyId][$key]['amount'] += $tax['amount'];\\
                    $taxTotals[$currencyId][$key]['paid'] += $tax['paid'];\\
                } else {\\
                    $taxTotals[$currencyId][$key] = $tax;\\
                }\\
            }\\
            foreach ($taxTotals as $currencyId => $taxes) {\\
                foreach ($taxes as $tax) {\\
                    $this->data[] = [\\
                       "",\\
                        $this->isExport ? $client->getDisplayName() : $client->present()->link,                            \\
                       $this->isExport ? $invoice->invoice_date : $invoice->present()->invoice_date,\\
                       $payment ? ($this->isExport ? $payment->payment_date : $payment->present()->payment_date) : '',\\
                        $this->isExport ? $invoice->invoice_number : $invoice->present()->link,\\
                       $tax['name'],\\
                        $tax['rate'] . '%',\\
                        $account->formatMoney($tax['amount'], $client),\\
                        $account->formatMoney($tax['paid'], $client),\\
                        $invoice->present()->amount,\\
                        $invoice->present()->paid,\\
                    ];\\
                    $this->addToTotals($client->currency_id, 'amount', $tax['amount']);\\
                    $this->addToTotals($client->currency_id, 'paid', $tax['paid']);\\
                    $dimension = $this->getDimension($client);\\
                    $this->addChartData($dimension, $invoice->invoice_date, $tax['amount']);\\
                }\\
            }\\
        }\\
    }\\
   $hasTaxRates = TaxRate::scope()->count();\\
   $expenses = Expense::scope()\\
                    ->orderBy('expense_date', 'desc')\\
                    ->withArchived()\\
                    ->with('client.contacts', 'vendor', 'expense_category', 'user')\\
                    ->where('expense_date', '>=', $this->startDate)\\
                    ->where('expense_date', '<=', $this->endDate);\\
                   \\
   foreach ($expenses->get() as $expense) {\\
        $amount = $expense->amountWithTax();\\
       $this->data[] = [\\
       $expense->vendor ? ($this->isExport ? $expense->vendor->name : $expense->vendor->present()->link) : '',\\
       $expense->client ? ($this->isExport ? $expense->client->getDisplayName() : $expense->client->present()->link) : '',\\
       $this->isExport ? $expense->expense_date() : link_to($expense->present()->url, $expense->present()->expense_date),\\
       $expense->present()->payment_date(),\\
       "",\\
        $hasTaxRates ? ($expense->taxAmount()  / ($expense->amountWithTax() - $expense->taxAmount() )) * 100 . '%' : "",\\
       $this->isExport ? $expense->taxAmount() * - 1 : $expense->present()->taxAmount(),\\
       $this->isExport ? ( $expense->isPaid() ? $expense->taxAmount() * - 1 : "" ) :  '-' . $expense->present()->taxAmount(),\\
       $this->isExport ? $expense->amount() : $expense->present()->amount(),\\
       $this->isExport ? ( $expense->isPaid() ? $expense->amount() : "" ) : ( $expense->isPaid() ? '-' . $expense->present()->amount() : "" ),            \\
       ];\\
       $this->addToTotals($expense->expense_currency_id, 'amount', $expense->taxAmount() * -1 );\\
        $expense->isPaid() ?: $this->addToTotals($expense->expense_currency_id, 'paid', $expense->taxAmount() * -1);\\
   }\\
   \\
   \\
}\\
}
