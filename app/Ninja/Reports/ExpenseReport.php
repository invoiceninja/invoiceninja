<?php

namespace App\Ninja\Reports;

use Barracuda\ArchiveStream\Archive;
use App\Models\Expense;
use App\Models\TaxRate;
use Auth;
use Utils;

class ExpenseReport extends AbstractReport
{
    public function getColumns()
    {
        $columns = [
            'vendor' => [],
            'client' => [],
            'date' => [],
            'category' => [],
            'amount' => [],
            'public_notes' => ['columnSelector-false'],
            'private_notes' => ['columnSelector-false'],
            'user' => ['columnSelector-false'],
            'payment_date' => ['columnSelector-false'],
            'payment_type' => ['columnSelector-false'],
            'payment_reference' => ['columnSelector-false'],

        ];

        $user = auth()->user();
        $account = $user->account;

        if ($account->customLabel('expense1')) {
            $columns[$account->present()->customLabel('expense1')] = ['columnSelector-false', 'custom'];
        }
        if ($account->customLabel('expense2')) {
            $columns[$account->present()->customLabel('expense2')] = ['columnSelector-false', 'custom'];
        }

        if (TaxRate::scope()->count()) {
            $columns['tax'] = ['columnSelector-false'];
        }

        if ($this->isExport) {
            $columns['currency'] = ['columnSelector-false'];
        }

        return $columns;
    }

    public function run()
    {
        $account = Auth::user()->account;
        $exportFormat = $this->options['export_format'];
        $subgroup = $this->options['subgroup'];
        $with = ['client.contacts', 'vendor'];
        $hasTaxRates = TaxRate::scope()->count();

        if ($exportFormat == 'zip') {
            $with[] = ['documents'];
        }

        $expenses = Expense::scope()
                        ->orderBy('expense_date', 'desc')
                        ->withArchived()
                        ->with('client.contacts', 'vendor', 'expense_category', 'user')
                        ->where('expense_date', '>=', $this->startDate)
                        ->where('expense_date', '<=', $this->endDate);

        if ($this->isExport && $exportFormat == 'zip') {
            if (! extension_loaded('GMP')) {
                die(trans('texts.gmp_required'));
            }

            $zip = Archive::instance_by_useragent(date('Y-m-d') . '_' . str_replace(' ', '_', trans('texts.expense_documents')));
            foreach ($expenses->get() as $expense) {
                foreach ($expense->documents as $document) {
                    $expenseId = str_pad($expense->public_id, $account->invoice_number_padding, '0', STR_PAD_LEFT);
                    $name = sprintf('%s_%s_%s_%s', $expense->expense_date ?: date('Y-m-d'), trans('texts.expense'), $expenseId, $document->name);
                    $name = str_replace(' ', '_', $name);
                    $zip->add_file($name, $document->getRaw());
                }
            }
            $zip->finish();
            exit;
        }

        foreach ($expenses->get() as $expense) {
            $amount = $expense->amountWithTax();

            $row = [
                $expense->vendor ? ($this->isExport ? $expense->vendor->name : $expense->vendor->present()->link) : '',
                $expense->client ? ($this->isExport ? $expense->client->getDisplayName() : $expense->client->present()->link) : '',
                $this->isExport ? $expense->expense_date : link_to($expense->present()->url, $expense->present()->expense_date),
                $expense->present()->category,
                Utils::formatMoney($amount, $expense->expense_currency_id),
                $expense->public_notes,
                $expense->private_notes,
                $expense->user->getDisplayName(),
                $expense->present()->payment_date(),
                $expense->present()->payment_type(),
                $expense->transaction_reference,
            ];

            if ($account->customLabel('expense1')) {
                $row[] = $expense->custom_value1;
            }
            if ($account->customLabel('expense2')) {
                $row[] = $expense->custom_value2;
            }

            if ($hasTaxRates) {
                $row[] = $expense->present()->taxAmount;
            }

            if ($this->isExport) {
                $row[] = $expense->present()->currencyCode;
            }

            $this->data[] = $row;

            $this->addToTotals($expense->expense_currency_id, 'amount', $amount);
            $this->addToTotals($expense->invoice_currency_id, 'amount', 0);

            if ($subgroup == 'category') {
                $dimension = $expense->present()->category;
            } elseif ($subgroup == 'vendor') {
                $dimension = $expense->vendor ? $expense->vendor->name : trans('texts.unset');
            } else {
                $dimension = $this->getDimension($expense);
            }

            $this->addChartData($dimension, $expense->expense_date, $amount);
        }
    }
}
