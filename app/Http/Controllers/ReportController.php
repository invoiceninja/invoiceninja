<?php

namespace App\Http\Controllers;

use App\Jobs\ExportReportResults;
use App\Jobs\LoadPostmarkStats;
use App\Jobs\RunReport;
use App\Models\Account;
use App\Models\ScheduledReport;
use Auth;
use Input;
use Utils;
use View;
use Carbon;
use Validator;


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
                            ->with(['clients.invoices.invoice_items', 'clients.contacts', 'clients.currency'])
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
        if (! Auth::user()->hasPermission('view_reports')) {
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
            $startDate = Utils::today(false)->modify('-1 month');
            $endDate = Utils::today(false);
        }

        $reportTypes = [
            'activity',
            'aging',
            'client',
            'credit',
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
            $config = [
                'date_field' => $dateField,
                'status_ids' => request()->status_ids,
                'group' => request()->group,
                'subgroup' => request()->subgroup,
                'document_filter' => request()->document_filter,
                'currency_type' => request()->currency_type,
                'export_format' => $format,
                'start_date' => $params['startDate'],
                'end_date' => $params['endDate'],
            ];
            $report = dispatch_now(new RunReport(auth()->user(), $reportType, $config, $isExport));
            $params = array_merge($params, $report->exportParams);
            switch ($action) {
                case 'export':
                    return dispatch_now(new ExportReportResults(auth()->user(), $format, $reportType, $params))->export($format);
                    break;
                case 'schedule':
                    self::schedule($params, $config);
                    return redirect('/reports');
                    break;
                case 'cancel_schedule':
                    self::cancelSchdule();
                    return redirect('/reports');
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
        $validator = Validator::make(request()->all(), [
            'frequency' => 'required|in:daily,weekly,biweekly,monthly',
            'send_date' => 'required',
        ]);

        if ($validator->fails()) {
            session()->now('message', trans('texts.scheduled_report_error'));
        } else {
            $options['report_type'] = $params['reportType'];
            $options['range'] = request('range');
            $options['start_date_offset'] = $options['range'] ? '' : Carbon::parse($params['startDate'])->diffInDays(null, false); // null,false to get the relative/non-absolute diff
            $options['end_date_offset'] = $options['range'] ? '' : Carbon::parse($params['endDate'])->diffInDays(null, false);

            unset($options['start_date']);
            unset($options['end_date']);
            unset($options['group']);
            unset($options['subgroup']);

            $schedule = ScheduledReport::createNew();
            $schedule->config = json_encode($options);
            $schedule->frequency = request('frequency');
            $schedule->send_date = Utils::toSqlDate(request('send_date'));
            $schedule->ip = request()->getClientIp();
            $schedule->save();

            session()->flash('message', trans('texts.created_scheduled_report'));
        }
    }

    private function cancelSchdule()
    {
        ScheduledReport::scope()
            ->whereUserId(auth()->user()->id)
            ->wherePublicId(request('scheduled_report_id'))
            ->delete();

        session()->flash('message', trans('texts.deleted_scheduled_report'));
    }

    public function showEmailReport()
    {
        $data = [
            'account' => auth()->user()->account,
        ];

        return view('reports.emails', $data);
    }

    public function loadEmailReport($startDate, $endDate)
    {
        $data = dispatch_now(new LoadPostmarkStats($startDate, $endDate));

        return response()->json($data);
    }
}
