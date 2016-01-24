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
            $startDate = Utils::toSqlDate(Input::get('start_date'), false);
            $endDate = Utils::toSqlDate(Input::get('end_date'), false);
            $enableReport = Input::get('enable_report') ? true : false;
            $enableChart = Input::get('enable_chart') ? true : false;
        } else {
            $groupBy = 'MONTH';
            $chartType = 'Bar';
            $reportType = ENTITY_INVOICE;
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
                $params = array_merge($params, self::generateReport($reportType, $groupBy, $startDate, $endDate));

                if ($action == 'export') {
                    self::export($params['exportData'], $params['reportTotals']);
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

    private function generateReport($reportType, $groupBy, $startDate, $endDate)
    {
        if ($reportType == ENTITY_CLIENT) {
            $columns = ['client', 'amount', 'paid', 'balance'];
        } elseif ($reportType == ENTITY_INVOICE) {
            $columns = ['client', 'invoice_number', 'invoice_date', 'amount', 'paid', 'balance'];
        } else {
            $columns = ['client', 'invoice_number', 'invoice_date', 'amount', 'payment_date', 'paid', 'method'];
        }

        $query = DB::table('invoices')
                        ->join('accounts', 'accounts.id', '=', 'invoices.account_id')
                        ->join('clients', 'clients.id', '=', 'invoices.client_id')
                        ->join('contacts', 'contacts.client_id', '=', 'clients.id')
                        ->where('invoices.account_id', '=', Auth::user()->account_id)
                        ->where('invoices.is_deleted', '=', false)
                        ->where('clients.is_deleted', '=', false)
                        ->where('contacts.deleted_at', '=', null)
                        ->where('invoices.invoice_date', '>=', $startDate->format('Y-m-d'))
                        ->where('invoices.invoice_date', '<=', $endDate->format('Y-m-d'))
                        ->where('invoices.is_quote', '=', false)
                        ->where('invoices.is_recurring', '=', false)
                        ->where('contacts.is_primary', '=', true);

        $select = [
            DB::raw('COALESCE(clients.currency_id, accounts.currency_id) currency_id'),
            'accounts.country_id',
            'contacts.first_name',
            'contacts.last_name',
            'contacts.email',
            'clients.name as client_name',
            'clients.public_id as client_public_id',
            'invoices.public_id as invoice_public_id'
        ];

        if ($reportType == ENTITY_CLIENT) {
            $query->groupBy('clients.id');
            array_push($select, DB::raw('sum(invoices.amount) amount'), DB::raw('sum(invoices.balance) balance'), DB::raw('sum(invoices.amount - invoices.balance) paid'));
        } else {
            $query->orderBy('invoices.id');
            array_push($select, 'invoices.invoice_number', 'invoices.amount', 'invoices.balance', 'invoices.invoice_date');
            if ($reportType == ENTITY_INVOICE) {
                array_push($select, DB::raw('(invoices.amount - invoices.balance) paid'));
            } else {
                $query->join('payments', 'payments.invoice_id', '=', 'invoices.id')
                        ->leftJoin('payment_types', 'payment_types.id', '=', 'payments.payment_type_id')
                        ->leftJoin('account_gateways', 'account_gateways.id', '=', 'payments.account_gateway_id')
                        ->leftJoin('gateways', 'gateways.id', '=', 'account_gateways.gateway_id');
                array_push($select, 'payments.payment_date', 'payments.amount as paid', 'payment_types.name as payment_type', 'gateways.name as gateway');
            }
        }

        $query->select($select);
        $data = $query->get();

        $lastInvoiceId = null;
        $sameAsLast = false;
        $displayData = [];

        $exportData = [];
        $reportTotals = [
                    'amount' => [],
                    'balance' => [],
                    'paid' => [],
                ];

        foreach ($data as $record) {
            $sameAsLast = ($lastInvoiceId == $record->invoice_public_id);
            $lastInvoiceId = $record->invoice_public_id;

            $displayRow = [];
            if ($sameAsLast) {
                array_push($displayRow, '', '', '', '');
            } else {
                array_push($displayRow, link_to('/clients/'.$record->client_public_id, Utils::getClientDisplayName($record)));
                if ($reportType != ENTITY_CLIENT) {
                    array_push($displayRow,
                        link_to('/invoices/'.$record->invoice_public_id, $record->invoice_number),
                        Utils::fromSqlDate($record->invoice_date, true)
                    );
                }
                array_push($displayRow, Utils::formatMoney($record->amount, $record->currency_id, $record->country_id));
            }
            if ($reportType != ENTITY_PAYMENT) {
                array_push($displayRow, Utils::formatMoney($record->paid, $record->currency_id, $record->country_id));
            }
            if ($reportType == ENTITY_PAYMENT) {
                array_push($displayRow,
                    Utils::fromSqlDate($record->payment_date, true),
                    Utils::formatMoney($record->paid, $record->currency_id, $record->country_id),
                    $record->gateway ?: $record->payment_type
                );
            } else {
                array_push($displayRow, Utils::formatMoney($record->balance, $record->currency_id, $record->country_id));
            }

            // export data
            $exportRow = [];
            if ($sameAsLast) {
                $exportRow[trans('texts.client')] = ' ';
                $exportRow[trans('texts.invoice_number')] = ' ';
                $exportRow[trans('texts.invoice_date')] = ' ';
                $exportRow[trans('texts.amount')] = ' ';
            } else {
                $exportRow[trans('texts.client')] = Utils::getClientDisplayName($record);
                if ($reportType != ENTITY_CLIENT) {
                    $exportRow[trans('texts.invoice_number')] = $record->invoice_number;
                    $exportRow[trans('texts.invoice_date')] = Utils::fromSqlDate($record->invoice_date, true);
                }
                $exportRow[trans('texts.amount')] = Utils::formatMoney($record->amount, $record->currency_id, $record->country_id);
            }
            if ($reportType != ENTITY_PAYMENT) {
                $exportRow[trans('texts.paid')] = Utils::formatMoney($record->paid, $record->currency_id, $record->country_id);
            }
            if ($reportType == ENTITY_PAYMENT) {
                $exportRow[trans('texts.payment_date')] = Utils::fromSqlDate($record->payment_date, true);
                $exportRow[trans('texts.payment_amount')] = Utils::formatMoney($record->paid, $record->currency_id, $record->country_id);
                $exportRow[trans('texts.method')] = $record->gateway ?: $record->payment_type;
            } else {
                $exportRow[trans('texts.balance')] = Utils::formatMoney($record->balance, $record->currency_id, $record->country_id);
            }

            $displayData[] = $displayRow;
            $exportData[] = $exportRow;

            $accountCurrencyId = Auth::user()->account->currency_id;
            $currencyId = $record->currency_id ? $record->currency_id : ($accountCurrencyId ? $accountCurrencyId : DEFAULT_CURRENCY);
            if (!isset($reportTotals['amount'][$currencyId])) {
                $reportTotals['amount'][$currencyId] = 0;
                $reportTotals['balance'][$currencyId] = 0;
                $reportTotals['paid'][$currencyId] = 0;
            }
            if (!$sameAsLast) {
                $reportTotals['amount'][$currencyId] += $record->amount;
                $reportTotals['balance'][$currencyId] += $record->balance;
            }
            $reportTotals['paid'][$currencyId] += $record->paid;
        }

        return [
            'columns' => $columns,
            'displayData' => $displayData,
            'reportTotals' => $reportTotals,
            'exportData' => $exportData
        ];
    }

    private function export($data, $totals)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        header('Content-Type:application/csv');
        header('Content-Disposition:attachment;filename=ninja-report.csv');

        Utils::exportData($output, $data);

        foreach (['amount', 'paid', 'balance'] as $type) {
            $csv = trans("texts.{$type}").',';
            foreach ($totals[$type] as $currencyId => $amount) {
                $csv .= Utils::formatMoney($amount, $currencyId).',';
            }
            fwrite($output, $csv."\n");
        }

        fclose($output);
        exit;
    }
}
