<?php

namespace App\Http\Controllers;

use App\Jobs\ExportReportResults;
use App\Models\Account;
use App\Models\ScheduledReport;
use Auth;
use Input;
use Str;
use Utils;
use View;
use Carbon;

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
        $format = Input::get('format');

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
            'document',
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
                'document_filter' => request()->document_filter,
                'currency_type' => request()->currency_type,
                'export_format' => $format,
            ];
            $report = new $reportClass($startDate, $endDate, $isExport, $options);
            if (Input::get('report_type')) {
                $report->run();
            }
            $params['report'] = $report;
            $params = array_merge($params, $report->results());
            switch ($action) {
                case 'export':
                    return dispatch(new ExportReportResults(auth()->user(), $format, $reportType, $params))->export($format);
                    break;
                case 'schedule':
                    self::schedule($params, $options);
                    break;
                case 'cancel_schedule':
                    self::cancelSchdule();
                    break;
            }
        } else {
            $params['columns'] = [];
            $params['displayData'] = [];
            $params['reportTotals'] = [];
            $params['report'] = false;
        }

        $params['scheduledReports'] = ScheduledReport::scope()->whereUserId(auth()->user()->id)->get();

        return View::make('reports.report_builder', $params);
    }

    private function schedule($params, $options)
    {
        $options['report_type'] = $params['reportType'];
        $options['range'] = request('range');
        $options['start_date'] = $options['range'] ? '' : Carbon::parse($params['startDate'])->diffInDays(null, false); // null,false to get the relative/non-absolute diff
        $options['end_date'] = $options['range'] ? '' : Carbon::parse($params['endDate'])->diffInDays(null, false);

        $schedule = ScheduledReport::createNew();
        $schedule->config = json_encode($options);
        $schedule->frequency = request('frequency');
        $schedule->send_date = Utils::toSqlDate(request('send_date'));
        $schedule->save();

        session()->now('message', trans('texts.created_scheduled_report'));
    }

    private function cancelSchdule()
    {
        ScheduledReport::scope()
            ->whereUserId(auth()->user()->id)
            ->wherePublicId(request('scheduled_report_id'))
            ->delete();

        session()->now('message', trans('texts.deleted_scheduled_report'));
    }
}
