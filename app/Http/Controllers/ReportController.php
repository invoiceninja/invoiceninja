<?php namespace App\Http\Controllers;

use Auth;
use Config;
use Input;
use Utils;
use DB;
use Session;
use View;
use App\Models\Account;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Task;

/**
 * Class ReportController
 */
class ReportController extends BaseController
{
    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function d3()
    {
        $message = '';
        $fileName = storage_path().'/dataviz_sample.txt';

        if (Auth::user()->account->hasFeature(FEATURE_REPORTS)) {
            $account = Account::where('id', '=', Auth::user()->account->id)
                            ->with(['clients.invoices.invoice_items', 'clients.contacts'])
                            ->first();
            $account = $account->hideFieldsForViz();
            $clients = $account->clients->toJson();
        } elseif (file_exists($fileName)) {
            $clients = file_get_contents($fileName);
            $message = trans('texts.sample_data');
        } else {
            $clients = '[]';
        }

        $data = [
            'clients' => $clients,
            'message' => $message,
        ];

        return View::make('reports.d3', $data);
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function showReports()
    {
        $action = Input::get('action');

        if (Input::get('report_type')) {
            $reportType = Input::get('report_type');
            $dateField = Input::get('date_field');
            $startDate = date_create(Input::get('start_date'));
            $endDate = date_create(Input::get('end_date'));
        } else {
            $reportType = ENTITY_INVOICE;
            $dateField = FILTER_INVOICE_DATE;
            $startDate = Utils::today(false)->modify('-3 month');
            $endDate = Utils::today(false);
        }

        $reportTypes = [
            ENTITY_CLIENT => trans('texts.client'),
            ENTITY_INVOICE => trans('texts.invoice'),
            ENTITY_PRODUCT => trans('texts.product'),
            ENTITY_PAYMENT => trans('texts.payment'),
            ENTITY_EXPENSE => trans('texts.expense'),
            ENTITY_TASK => trans('texts.task'),
            ENTITY_TAX_RATE => trans('texts.tax'),
        ];

        $params = [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'reportTypes' => $reportTypes,
            'reportType' => $reportType,
            'title' => trans('texts.charts_and_reports'),
            'account' => Auth::user()->account,
        ];

        if (Auth::user()->account->hasFeature(FEATURE_REPORTS)) {
            $isExport = $action == 'export';
            $params = array_merge($params, self::generateReport($reportType, $startDate, $endDate, $dateField, $isExport));

            if ($isExport) {
                self::export($reportType, $params['displayData'], $params['columns'], $params['reportTotals']);
            }
        } else {
            $params['columns'] = [];
            $params['displayData'] = [];
            $params['reportTotals'] = [];
        }

        return View::make('reports.chart_builder', $params);
    }

    /**
     * @param $reportType
     * @param $startDate
     * @param $endDate
     * @param $dateField
     * @param $isExport
     * @return array
     */
    private function generateReport($reportType, $startDate, $endDate, $dateField, $isExport)
    {
        if ($reportType == ENTITY_CLIENT) {
            return $this->generateClientReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_INVOICE) {
            return $this->generateInvoiceReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_PRODUCT) {
            return $this->generateProductReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_PAYMENT) {
            return $this->generatePaymentReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_TAX_RATE) {
            return $this->generateTaxRateReport($startDate, $endDate, $dateField, $isExport);
        } elseif ($reportType == ENTITY_EXPENSE) {
            return $this->generateExpenseReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_TASK) {
            return $this->generateTaskReport($startDate, $endDate, $isExport);
        }
    }

    private function generateTaskReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'date', 'description', 'duration'];
        $displayData = [];

        $tasks = Task::scope()
                    ->with('client.contacts')
                    ->withArchived()
                    ->dateRange($startDate, $endDate);

        foreach ($tasks->get() as $task) {
            $displayData[] = [
                $task->client ? ($isExport ? $task->client->getDisplayName() : $task->client->present()->link) : trans('texts.unassigned'),
                link_to($task->present()->url, $task->getStartTime()),
                $task->present()->description,
                Utils::formatTime($task->getDuration()),
            ];
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => [],
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $dateField
     * @param $isExport
     * @return array
     */
    private function generateTaxRateReport($startDate, $endDate, $dateField, $isExport)
    {
        $columns = ['tax_name', 'tax_rate', 'amount', 'paid'];

        $account = Auth::user()->account;
        $displayData = [];
        $reportTotals = [];

        $clients = Client::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) use ($startDate, $endDate, $dateField) {
                            $query->with('invoice_items')->withArchived();
                            if ($dateField == FILTER_INVOICE_DATE) {
                                $query->where('invoice_date', '>=', $startDate)
                                      ->where('invoice_date', '<=', $endDate)
                                      ->with('payments');
                            } else {
                                $query->whereHas('payments', function($query) use ($startDate, $endDate) {
                                            $query->where('payment_date', '>=', $startDate)
                                                  ->where('payment_date', '<=', $endDate)
                                                  ->withArchived();
                                        })
                                        ->with(['payments' => function($query) use ($startDate, $endDate) {
                                            $query->where('payment_date', '>=', $startDate)
                                                  ->where('payment_date', '<=', $endDate)
                                                  ->withArchived();
                                        }]);
                            }
                        }]);

        foreach ($clients->get() as $client) {
            $currencyId = $client->currency_id ?: Auth::user()->account->getCurrencyId();
            $amount = 0;
            $paid = 0;
            $taxTotals = [];

            foreach ($client->invoices as $invoice) {
                foreach ($invoice->getTaxes(true) as $key => $tax) {
                    if ( ! isset($taxTotals[$currencyId])) {
                        $taxTotals[$currencyId] = [];
                    }
                    if (isset($taxTotals[$currencyId][$key])) {
                        $taxTotals[$currencyId][$key]['amount'] += $tax['amount'];
                        $taxTotals[$currencyId][$key]['paid'] += $tax['paid'];
                    } else {
                        $taxTotals[$currencyId][$key] = $tax;
                    }
                }

                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            foreach ($taxTotals as $currencyId => $taxes) {
                foreach ($taxes as $tax) {
                    $displayData[] = [
                        $tax['name'],
                        $tax['rate'] . '%',
                        $account->formatMoney($tax['amount'], $client),
                        $account->formatMoney($tax['paid'], $client)
                    ];
                }

                $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'amount', $tax['amount']);
                $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'paid', $tax['paid']);
            }
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];

    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generatePaymentReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'invoice_number', 'invoice_date', 'amount', 'payment_date', 'paid', 'method'];

        $account = Auth::user()->account;
        $displayData = [];
        $reportTotals = [];

        $payments = Payment::scope()
                        ->withArchived()
                        ->excludeFailed()
                        ->whereHas('client', function($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->whereHas('invoice', function($query) {
                            $query->where('is_deleted', '=', false);
                        })
                        ->with('client.contacts', 'invoice', 'payment_type', 'account_gateway.gateway')
                        ->where('payment_date', '>=', $startDate)
                        ->where('payment_date', '<=', $endDate);

        foreach ($payments->get() as $payment) {
            $invoice = $payment->invoice;
            $client = $payment->client;
            $displayData[] = [
                $isExport ? $client->getDisplayName() : $client->present()->link,
                $isExport ? $invoice->invoice_number : $invoice->present()->link,
                $invoice->present()->invoice_date,
                $account->formatMoney($invoice->amount, $client),
                $payment->present()->payment_date,
                $account->formatMoney($payment->getCompletedAmount(), $client),
                $payment->present()->method,
            ];

            $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'amount', $invoice->amount);
            $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'paid', $payment->getCompletedAmount());
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generateInvoiceReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'invoice_number', 'invoice_date', 'amount', 'payment_date', 'paid', 'method'];

        $account = Auth::user()->account;
        $displayData = [];
        $reportTotals = [];

        $clients = Client::scope()
                        ->withTrashed()
                        ->with('contacts')
                        ->where('is_deleted', '=', false)
                        ->with(['invoices' => function($query) use ($startDate, $endDate) {
                            $query->invoices()
                                  ->withArchived()
                                  ->where('invoice_date', '>=', $startDate)
                                  ->where('invoice_date', '<=', $endDate)
                                  ->with(['payments' => function($query) {
                                        $query->withArchived()
                                              ->excludeFailed()
                                              ->with('payment_type', 'account_gateway.gateway');
                                  }, 'invoice_items'])
                                  ->withTrashed();
                        }]);

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {

                $payments = count($invoice->payments) ? $invoice->payments : [false];
                foreach ($payments as $payment) {
                    $displayData[] = [
                        $isExport ? $client->getDisplayName() : $client->present()->link,
                        $isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        $account->formatMoney($invoice->amount, $client),
                        $payment ? $payment->present()->payment_date : '',
                        $payment ? $account->formatMoney($payment->getCompletedAmount(), $client) : '',
                        $payment ? $payment->present()->method : '',
                    ];
                    $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'paid', $payment ? $payment->getCompletedAmount() : 0);
                }

                $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'amount', $invoice->amount);
                $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'balance', $invoice->balance);
            }
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generateProductReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'invoice_number', 'invoice_date', 'quantity', 'product'];

        $account = Auth::user()->account;
        $displayData = [];
        $reportTotals = [];

        $clients = Client::scope()
                        ->withTrashed()
                        ->with('contacts')
                        ->where('is_deleted', '=', false)
                        ->with(['invoices' => function($query) use ($startDate, $endDate) {
                            $query->where('invoice_date', '>=', $startDate)
                                  ->where('invoice_date', '<=', $endDate)
                                  ->where('is_deleted', '=', false)
                                  ->where('is_recurring', '=', false)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->with(['invoice_items'])
                                  ->withTrashed();
                        }]);

        foreach ($clients->get() as $client) {
            foreach ($client->invoices as $invoice) {

                foreach ($invoice->invoice_items as $invoiceItem) {
                    $displayData[] = [
                        $isExport ? $client->getDisplayName() : $client->present()->link,
                        $isExport ? $invoice->invoice_number : $invoice->present()->link,
                        $invoice->present()->invoice_date,
                        round($invoiceItem->qty, 2),
                        $invoiceItem->product_key,
                    ];
                    //$reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'paid', $payment ? $payment->amount : 0);
                }

                //$reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'amount', $invoice->amount);
                //$reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'balance', $invoice->balance);
            }
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => [],
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generateClientReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'amount', 'paid', 'balance'];

        $account = Auth::user()->account;
        $displayData = [];
        $reportTotals = [];

        $clients = Client::scope()
                        ->withArchived()
                        ->with('contacts')
                        ->with(['invoices' => function($query) use ($startDate, $endDate) {
                            $query->where('invoice_date', '>=', $startDate)
                                  ->where('invoice_date', '<=', $endDate)
                                  ->where('invoice_type_id', '=', INVOICE_TYPE_STANDARD)
                                  ->where('is_recurring', '=', false)
                                  ->withArchived();
                        }]);

        foreach ($clients->get() as $client) {
            $amount = 0;
            $paid = 0;

            foreach ($client->invoices as $invoice) {
                $amount += $invoice->amount;
                $paid += $invoice->getAmountPaid();
            }

            $displayData[] = [
                $isExport ? $client->getDisplayName() : $client->present()->link,
                $account->formatMoney($amount, $client),
                $account->formatMoney($paid, $client),
                $account->formatMoney($amount - $paid, $client)
            ];

            $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'amount', $amount);
            $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'paid', $paid);
            $reportTotals = $this->addToTotals($reportTotals, $client->currency_id, 'balance', $amount - $paid);
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $isExport
     * @return array
     */
    private function generateExpenseReport($startDate, $endDate, $isExport)
    {
        $columns = ['vendor', 'client', 'date', 'expense_amount'];

        $account = Auth::user()->account;
        $displayData = [];
        $reportTotals = [];

        $expenses = Expense::scope()
                        ->withArchived()
                        ->with('client.contacts', 'vendor')
                        ->where('expense_date', '>=', $startDate)
                        ->where('expense_date', '<=', $endDate);


        foreach ($expenses->get() as $expense) {
            $amount = $expense->amountWithTax();

            $displayData[] = [
                $expense->vendor ? ($isExport ? $expense->vendor->name : $expense->vendor->present()->link) : '',
                $expense->client ? ($isExport ? $expense->client->getDisplayName() : $expense->client->present()->link) : '',
                $expense->present()->expense_date,
                Utils::formatMoney($amount, $expense->currency_id),
            ];

            $reportTotals = $this->addToTotals($reportTotals, $expense->expense_currency_id, 'amount', $amount);
            $reportTotals = $this->addToTotals($reportTotals, $expense->invoice_currency_id, 'amount', 0);
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    /**
     * @param $data
     * @param $currencyId
     * @param $field
     * @param $value
     * @return mixed
     */
    private function addToTotals($data, $currencyId, $field, $value) {
        $currencyId = $currencyId ?: Auth::user()->account->getCurrencyId();

        if (!isset($data[$currencyId][$field])) {
            $data[$currencyId][$field] = 0;
        }

        $data[$currencyId][$field] += $value;

        return $data;
    }

    /**
     * @param $reportType
     * @param $data
     * @param $columns
     * @param $totals
     */
    private function export($reportType, $data, $columns, $totals)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        $reportType = trans("texts.{$reportType}s");
        $date = date('Y-m-d');

        header('Content-Type:application/csv');
        header("Content-Disposition:attachment;filename={$date}_Ninja_{$reportType}.csv");

        Utils::exportData($output, $data, Utils::trans($columns));

        fwrite($output, trans('texts.totals'));
        foreach ($totals as $currencyId => $fields) {
            foreach ($fields as $key => $value) {
                fwrite($output, ',' . trans("texts.{$key}"));
            }
            fwrite($output, "\n");
            break;
        }

        foreach ($totals as $currencyId => $fields) {
            $csv = Utils::getFromCache($currencyId, 'currencies')->name . ',';
            foreach ($fields as $key => $value) {
                $csv .= '"' . Utils::formatMoney($value, $currencyId).'",';
            }
            fwrite($output, $csv."\n");
        }

        fclose($output);
        exit;
    }
}
