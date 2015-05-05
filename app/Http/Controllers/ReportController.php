<?php namespace App\Http\Controllers;

use Auth;
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

        if (Auth::user()->account->isPro()) {
            $account = Account::where('id', '=', Auth::user()->account->id)->with(['clients.invoices.invoice_items', 'clients.contacts'])->first();
            $account = $account->hideFieldsForViz();
            $clients = $account->clients->toJson();
        } elseif (isset($_ENV['DATA_VIZ_SAMPLE'])) {
            $clients = $_ENV['DATA_VIZ_SAMPLE'];
            $message = trans('texts.sample_data');
        } else {
            $clients = '[]';
        }

        $data = [
            'feature' => ACCOUNT_DATA_VISUALIZATIONS,
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
            $reportType = '';
            $startDate = Utils::today(false)->modify('-3 month');
            $endDate = Utils::today(false);
            $enableReport = true;
            $enableChart = true;
        }

        $datasets = [];
        $labels = [];
        $maxTotals = 0;
        $width = 10;

        $displayData = [];
        $exportData = [];
        $columns = [];
        $reportTotals = [
                    'amount' => [],
                    'balance' => [],
                    'paid' => []
                ];


        if (Auth::user()->account->isPro()) {

            if ($enableReport) {
                $query = DB::table('invoices')
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
                
                $select = ['clients.currency_id', 'contacts.first_name', 'contacts.last_name', 'contacts.email', 'clients.name as client_name', 'clients.public_id as client_public_id', 'invoices.public_id as invoice_public_id'];

                if ($reportType) {
                    $query->groupBy('clients.id');
                    array_push($select, DB::raw('sum(invoices.amount) amount'), DB::raw('sum(invoices.balance) balance'), DB::raw('sum(invoices.amount - invoices.balance) paid'));
                    $columns = ['client', 'amount', 'paid', 'balance'];
                } else {
                    array_push($select, 'invoices.invoice_number', 'invoices.amount', 'invoices.balance', 'invoices.invoice_date', DB::raw('(invoices.amount - invoices.balance) paid'));
                    $query->orderBy('invoices.id');
                    $columns = ['client', 'invoice_number', 'invoice_date', 'amount', 'paid', 'balance'];
                }
                                    
                $query->select($select);
                $data = $query->get();

                foreach ($data as $record) {
                    // web display data
                    $displayRow = [link_to('/clients/'.$record->client_public_id, Utils::getClientDisplayName($record))];
                    if (!$reportType) {
                        array_push($displayRow,
                            link_to('/invoices/'.$record->invoice_public_id, $record->invoice_number),
                            Utils::fromSqlDate($record->invoice_date, true)
                        );
                    }
                    array_push($displayRow,
                        Utils::formatMoney($record->amount, $record->currency_id),
                        Utils::formatMoney($record->paid, $record->currency_id),
                        Utils::formatMoney($record->balance, $record->currency_id)
                    );

                    // export data
                    $exportRow = [trans('texts.client') => Utils::getClientDisplayName($record)];
                    if (!$reportType) {
                        $exportRow[trans('texts.invoice_number')] = $record->invoice_number;
                        $exportRow[trans('texts.invoice_date')] = Utils::fromSqlDate($record->invoice_date, true);
                    }
                    $exportRow[trans('texts.amount')] = Utils::formatMoney($record->amount, $record->currency_id);
                    $exportRow[trans('texts.paid')] = Utils::formatMoney($record->paid, $record->currency_id);
                    $exportRow[trans('texts.balance')] = Utils::formatMoney($record->balance, $record->currency_id);

                    $displayData[] = $displayRow;
                    $exportData[] = $exportRow;

                    $accountCurrencyId = Auth::user()->account->currency_id;
                    $currencyId = $record->currency_id ? $record->currency_id : ($accountCurrencyId ? $accountCurrencyId : DEFAULT_CURRENCY);
                    if (!isset($reportTotals['amount'][$currencyId])) {
                        $reportTotals['amount'][$currencyId] = 0;
                        $reportTotals['balance'][$currencyId] = 0;
                        $reportTotals['paid'][$currencyId] = 0;
                    }
                    $reportTotals['amount'][$currencyId] += $record->amount;
                    $reportTotals['paid'][$currencyId] += $record->paid;
                    $reportTotals['balance'][$currencyId] += $record->balance;
                }

                if ($action == 'export') {
                    self::export($exportData, $reportTotals);
                }
            }

            if ($enableChart) {
                foreach ([ENTITY_INVOICE, ENTITY_PAYMENT, ENTITY_CREDIT] as $entityType) {
                    $records = DB::table($entityType.'s')
                                ->select(DB::raw('sum(amount) as total, concat(YEAR('.$entityType.'_date), '.$groupBy.'('.$entityType.'_date)) as '.$groupBy))
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
                    $dates = $records->lists($groupBy);
                    $data = array_combine($dates, $totals);

                    $padding = $groupBy == 'DAYOFYEAR' ? 'day' : ($groupBy == 'WEEK' ? 'week' : 'month');
                    $endDate->modify('+1 '.$padding);
                    $interval = new DateInterval('P1'.substr($groupBy, 0, 1));
                    $period = new DatePeriod($startDate, $interval, $endDate);
                    $endDate->modify('-1 '.$padding);

                    $totals = [];

                    foreach ($period as $d) {
                        $dateFormat = $groupBy == 'DAYOFYEAR' ? 'z' : ($groupBy == 'WEEK' ? 'W' : 'n');
                        $date = $d->format('Y'.$dateFormat);
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
            }
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
            '' => '',
            'Client' => trans('texts.client')
        ];

        $params = [
            'labels' => $labels,
            'datasets' => $datasets,
            'scaleStepWidth' => $width,
            'dateTypes' => $dateTypes,
            'chartTypes' => $chartTypes,
            'chartType' => $chartType,
            'startDate' => $startDate->format(Session::get(SESSION_DATE_FORMAT)),
            'endDate' => $endDate->format(Session::get(SESSION_DATE_FORMAT)),
            'groupBy' => $groupBy,
            'feature' => ACCOUNT_CHART_BUILDER,
            'displayData' => $displayData,
            'columns' => $columns,
            'reportTotals' => $reportTotals,
            'reportTypes' => $reportTypes,
            'reportType' => $reportType,
            'enableChart' => $enableChart,
            'enableReport' => $enableReport,
        ];

        return View::make('reports.chart_builder', $params);
    }

    private function export($data, $totals)
    {
        $output = fopen('php://output', 'w') or Utils::fatalError();
        header('Content-Type:application/csv');
        header('Content-Disposition:attachment;filename=ninja-report.csv');

        Utils::exportData($output, $data);

        foreach (['amount', 'paid', 'balance'] as $type) {
            $csv = trans("texts.{$type}") . ',';
            foreach ($totals[$type] as $currencyId => $amount) {
                $csv .= Utils::formatMoney($amount, $currencyId) . ',';
            }
            fwrite($output, $csv . "\n");
        }

        fclose($output);
        exit;
    }
}
