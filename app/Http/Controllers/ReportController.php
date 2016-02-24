<?php namespace App\Http\Controllers;

use Auth;
use Config;
use Input;
use Utils;
use DB;
use DateInterval;
use DatePeriod;
use Session;
use View;
use App\Models\Account;
use App\Models\Client;
use App\Models\Payment;

class ReportController extends BaseController
{
    public function d3()
    {
        $message = '';
        $fileName = storage_path().'/dataviz_sample.txt';

        if (Auth::user()->account->isPro()) {
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

    public function showReports()
    {
        $action = Input::get('action');

        if (Input::all()) {
            $groupBy = Input::get('group_by');
            $chartType = Input::get('chart_type');
            $reportType = Input::get('report_type');
            $dateField = Input::get('date_field');
            $startDate = Utils::toSqlDate(Input::get('start_date'), false);
            $endDate = Utils::toSqlDate(Input::get('end_date'), false);
            $enableReport = Input::get('enable_report') ? true : false;
            $enableChart = Input::get('enable_chart') ? true : false;
        } else {
            $groupBy = 'MONTH';
            $chartType = 'Bar';
            $reportType = ENTITY_INVOICE;
            $dateField = FILTER_INVOICE_DATE;
            $startDate = Utils::today(false)->modify('-3 month');
            $endDate = Utils::today(false);
            $enableReport = true;
            $enableChart = true;
        }

        $dateTypes = [
            'DAYOFYEAR' => 'Daily',
            'WEEK' => 'Weekly',
            'MONTH' => 'Monthly',
        ];

        $chartTypes = [
            'Bar' => 'Bar',
            'Line' => 'Line',
        ];

        $reportTypes = [
            ENTITY_CLIENT => trans('texts.client'),
            ENTITY_INVOICE => trans('texts.invoice'),
            ENTITY_PAYMENT => trans('texts.payment'),
            ENTITY_TAX_RATE => trans('texts.taxes'),
        ];

        $params = [
            'dateTypes' => $dateTypes,
            'chartTypes' => $chartTypes,
            'chartType' => $chartType,
            'startDate' => $startDate->format(Session::get(SESSION_DATE_FORMAT)),
            'endDate' => $endDate->format(Session::get(SESSION_DATE_FORMAT)),
            'groupBy' => $groupBy,
            'reportTypes' => $reportTypes,
            'reportType' => $reportType,
            'enableChart' => $enableChart,
            'enableReport' => $enableReport,
            'title' => trans('texts.charts_and_reports'),
        ];

        if (Auth::user()->account->isPro()) {
            if ($enableReport) {
                $isExport = $action == 'export';
                $params = array_merge($params, self::generateReport($reportType, $startDate, $endDate, $dateField, $isExport));

                if ($isExport) {
                    self::export($params['displayData'], $params['columns'], $params['reportTotals']);
                }
            }
            if ($enableChart) {
                $params = array_merge($params, self::generateChart($groupBy, $startDate, $endDate));
            }
        } else {
            $params['columns'] = [];
            $params['displayData'] = [];
            $params['reportTotals'] = [
                'amount' => [],
                'balance' => [],
                'paid' => [],
            ];
            $params['labels'] = [];
            $params['datasets'] = [];
            $params['scaleStepWidth'] = 100;
        }

        return View::make('reports.chart_builder', $params);
    }

    private function generateChart($groupBy, $startDate, $endDate)
    {
        $width = 10;
        $datasets = [];
        $labels = [];
        $maxTotals = 0;

        foreach ([ENTITY_INVOICE, ENTITY_PAYMENT, ENTITY_CREDIT] as $entityType) {
            // SQLite does not support the YEAR(), MONTH(), WEEK() and similar functions.
            // Let's see if SQLite is being used.
            if (Config::get('database.connections.'.Config::get('database.default').'.driver') == 'sqlite') {
                // Replace the unsupported function with it's date format counterpart
                switch ($groupBy) {
                    case 'MONTH':
                        $dateFormat = '%m';     // returns 01-12
                        break;
                    case 'WEEK':
                        $dateFormat = '%W';     // returns 00-53
                        break;
                    case 'DAYOFYEAR':
                        $dateFormat = '%j';     // returns 001-366
                        break;
                    default:
                        $dateFormat = '%m';     // MONTH by default
                        break;
                }

                // Concatenate the year and the chosen timeframe (Month, Week or Day)
                $timeframe = 'strftime("%Y", '.$entityType.'_date) || strftime("'.$dateFormat.'", '.$entityType.'_date)';
            } else {
                // Supported by Laravel's other DBMS drivers (MySQL, MSSQL and PostgreSQL)
                $timeframe = 'concat(YEAR('.$entityType.'_date), '.$groupBy.'('.$entityType.'_date))';
            }

            $records = DB::table($entityType.'s')
                ->select(DB::raw('sum(amount) as total, '.$timeframe.' as '.$groupBy))
                ->where('account_id', '=', Auth::user()->account_id)
                ->where($entityType.'s.is_deleted', '=', false)
                ->where($entityType.'s.'.$entityType.'_date', '>=', $startDate->format('Y-m-d'))
                ->where($entityType.'s.'.$entityType.'_date', '<=', $endDate->format('Y-m-d'))
                ->groupBy($groupBy);

            if ($entityType == ENTITY_INVOICE) {
                $records->where('is_quote', '=', false)
                        ->where('is_recurring', '=', false);
            }

            $totals = $records->lists('total');
            $dates  = $records->lists($groupBy);
            $data   = array_combine($dates, $totals);

            $padding = $groupBy == 'DAYOFYEAR' ? 'day' : ($groupBy == 'WEEK' ? 'week' : 'month');
            $endDate->modify('+1 '.$padding);
            $interval = new DateInterval('P1'.substr($groupBy, 0, 1));
            $period   = new DatePeriod($startDate, $interval, $endDate);
            $endDate->modify('-1 '.$padding);

            $totals = [];

            foreach ($period as $d) {
                $dateFormat = $groupBy == 'DAYOFYEAR' ? 'z' : ($groupBy == 'WEEK' ? 'W' : 'n');
                // MySQL returns 1-366 for DAYOFYEAR, whereas PHP returns 0-365
                $date = $groupBy == 'DAYOFYEAR' ? $d->format('Y').($d->format($dateFormat) + 1) : $d->format('Y'.$dateFormat);
                $totals[] = isset($data[$date]) ? $data[$date] : 0;

                if ($entityType == ENTITY_INVOICE) {
                    $labelFormat = $groupBy == 'DAYOFYEAR' ? 'j' : ($groupBy == 'WEEK' ? 'W' : 'F');
                    $label = $d->format($labelFormat);
                    $labels[] = $label;
                }
            }

            $max = max($totals);

            if ($max > 0) {
                $datasets[] = [
                    'totals' => $totals,
                    'colors' => $entityType == ENTITY_INVOICE ? '78,205,196' : ($entityType == ENTITY_CREDIT ? '199,244,100' : '255,107,107'),
                ];
                $maxTotals = max($max, $maxTotals);
            }
        }

        $width = (ceil($maxTotals / 100) * 100) / 10;
        $width = max($width, 10);

        return [
            'datasets' => $datasets,
            'scaleStepWidth' => $width,
            'labels' => $labels,
        ];
    }

    private function generateReport($reportType, $startDate, $endDate, $dateField, $isExport)
    {
        if ($reportType == ENTITY_CLIENT) {
            return $this->generateClientReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_INVOICE) {
            return $this->generateInvoiceReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_PAYMENT) {
            return $this->generatePaymentReport($startDate, $endDate, $isExport);
        } elseif ($reportType == ENTITY_TAX_RATE) {
            return $this->generateTaxRateReport($startDate, $endDate, $dateField, $isExport);
        }
    }

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
                            $query->withArchived();
                            if ($dateField == FILTER_PAYMENT_DATE) {
                                $query->where('invoice_date', '>=', $startDate)
                                      ->where('invoice_date', '<=', $endDate)
                                      ->whereHas('payments', function($query) use ($startDate, $endDate) {
                                            $query->where('payment_date', '>=', $startDate)
                                                  ->where('payment_date', '<=', $endDate)
                                                  ->withArchived();
                                        })
                                        ->with(['payments' => function($query) use ($startDate, $endDate) {
                                            $query->where('payment_date', '>=', $startDate)
                                                  ->where('payment_date', '<=', $endDate)
                                                  ->withArchived()
                                                  ->with('payment_type', 'account_gateway.gateway');
                                  }, 'invoice_items']);
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
                        $tax['rate'],
                        $account->formatMoney($tax['amount'], $client),
                        $account->formatMoney($tax['paid'], $client)
                    ];
                }
    
                $reportTotals = $this->addToTotals($reportTotals, $client, 'amount', $tax['amount']);
                $reportTotals = $this->addToTotals($reportTotals, $client, 'paid', $tax['paid']);
            }
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];

    }

    private function generatePaymentReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'invoice_number', 'invoice_date', 'amount', 'payment_date', 'paid', 'method'];

        $account = Auth::user()->account;
        $displayData = [];
        $reportTotals = [];
        
        $payments = Payment::scope()
                        ->withTrashed()
                        ->where('is_deleted', '=', false)
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
                $account->formatMoney($payment->amount, $client),
                $payment->present()->method,
            ];

            $reportTotals = $this->addToTotals($reportTotals, $client, 'amount', $invoice->amount);
            $reportTotals = $this->addToTotals($reportTotals, $client, 'paid', $payment->amount);
        }
        
        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    private function generateInvoiceReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'invoice_number', 'invoice_date', 'amount', 'paid', 'balance'];

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
                                  ->with(['payments' => function($query) {
                                        $query->withTrashed()
                                              ->with('payment_type', 'account_gateway.gateway')
                                              ->where('is_deleted', '=', false);
                                  }, 'invoice_items'])
                                  ->withTrashed();
                        }]);
        
        foreach ($clients->get() as $client) {
            $currencyId = $client->currency_id ?: Auth::user()->account->getCurrencyId();

            foreach ($client->invoices as $invoice) {
                $displayData[] = [
                    $isExport ? $client->getDisplayName() : $client->present()->link,
                    $isExport ? $invoice->invoice_number : $invoice->present()->link,
                    $invoice->present()->invoice_date,
                    $account->formatMoney($invoice->amount, $client),
                    $account->formatMoney($invoice->getAmountPaid(), $client),
                    $account->formatMoney($invoice->balance, $client),
                ];
                $reportTotals = $this->addToTotals($reportTotals, $client, 'amount', $invoice->amount);
                $reportTotals = $this->addToTotals($reportTotals, $client, 'paid', $invoice->getAmountPaid());
                $reportTotals = $this->addToTotals($reportTotals, $client, 'balance', $invoice->balance);
            }
        }
        
        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    private function generateClientReport($startDate, $endDate, $isExport)
    {
        $columns = ['client', 'amount', 'paid', 'balance'];

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
                                  ->withTrashed();
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

            $reportTotals = $this->addToTotals($reportTotals, $client, 'amount', $amount);
            $reportTotals = $this->addToTotals($reportTotals, $client, 'paid', $paid);
            $reportTotals = $this->addToTotals($reportTotals, $client, 'balance', $amount - $paid);
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
        ];
    }

    private function addToTotals($data, $client, $field, $value) {
        $currencyId = $client->currency_id ?: Auth::user()->account->getCurrencyId();

        if (!isset($data[$currencyId][$field])) {
            $data[$currencyId][$field] = 0;
        }

        $data[$currencyId][$field] += $value;

        return $data;
    }

    private function export($data, $columns, $totals)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        header('Content-Type:application/csv');
        header('Content-Disposition:attachment;filename=ninja-report.csv');

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
