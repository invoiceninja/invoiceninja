<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Auth;
use Input;
use Str;
use Utils;
use View;

/**
 * Class ReportController.
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
            $clients = $account->clients;
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
        if (! Auth::user()->hasPermission('view_all')) {
            return redirect('/');
        }

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
            'activity',
            'aging',
            'client',
            'expense',
            'invoice',
            'payment',
            'product',
            'profit_and_loss',
            'task',
            'tax_rate',
            'quote',
        ];

        $params = [
            'startDate' => $startDate->format('Y-m-d'),
            'endDate' => $endDate->format('Y-m-d'),
            'reportTypes' => array_combine($reportTypes, Utils::trans($reportTypes)),
            'reportType' => $reportType,
            'title' => trans('texts.charts_and_reports'),
            'account' => Auth::user()->account,
        ];

        if (Auth::user()->account->hasFeature(FEATURE_REPORTS)) {
            $isExport = $action == 'export';
            $reportClass = '\\App\\Ninja\\Reports\\' . Str::studly($reportType) . 'Report';
            $options = [
                'date_field' => $dateField,
                'invoice_status' => request()->invoice_status,
                'group_dates_by' => request()->group_dates_by,
            ];
            $report = new $reportClass($startDate, $endDate, $isExport, $options);
            if (Input::get('report_type')) {
                $report->run();
            }
            $params['report'] = $report;
            $params = array_merge($params, $report->results());
            if ($isExport) {
                self::export($reportType, $params['displayData'], $params['columns'], $params['reportTotals']);
            }
        } else {
            $params['columns'] = [];
            $params['displayData'] = [];
            $params['reportTotals'] = [];
            $params['report'] = false;
        }

        return View::make('reports.chart_builder', $params);
    }

    /**
     * @param $reportType
     * @param $data
     * @param $columns
     * @param $totals
     */
    private function export($reportType, $data, $columns, $totals)
    {
        if (! Auth::user()->hasPermission('view_all')) {
            exit;
        }

        $output = fopen('php://output', 'w') or Utils::fatalError();
        $date = date('Y-m-d');

        $columns = array_map(function($key, $val) {
            return is_array($val) ? $key : $val;
        }, array_keys($columns), $columns);

        header('Content-Type:application/csv');
        header("Content-Disposition:attachment;filename={$date}-invoiceninja-{$reportType}-report.csv");

        Utils::exportData($output, $data, Utils::trans($columns));

        /*
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
        */

        fclose($output);
        exit;
    }
}
