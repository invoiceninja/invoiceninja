<?php

namespace App\Http\Controllers;

use App\Models\Account;
use Auth;
use Input;
use Str;
use Utils;
use View;
use Excel;

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
                return self::export($format, $reportType, $params);
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
     * @param $format
     * @param $reportType
     * @param $params
     * @todo: Add summary to export
     */
    private function export($format, $reportType, $params)
    {
        if (! Auth::user()->hasPermission('view_all')) {
            exit;
        }

        $format  = strtolower($format);
        $data    = $params['displayData'];
        $columns = $params['columns'];
        $totals  = $params['reportTotals'];
        $report  = $params['report'];

        $filename = "{$params['startDate']}-{$params['endDate']}_invoiceninja-".strtolower(Utils::normalizeChars(trans("texts.$reportType")))."-report";

        $formats = ['csv', 'pdf', 'xlsx'];
        if(!in_array($format, $formats)) {
            throw new \Exception("Invalid format request to export report");
        }

        //Get labeled header
        $columns_labeled = $report->tableHeaderArray();

        /*$summary = [];
        if(count(array_values($totals))) {
            $summary[] = array_merge([
                trans("texts.totals")
            ], array_map(function ($key) {return trans("texts.{$key}");}, array_keys(array_values(array_values($totals)[0])[0])));
        }

        foreach ($totals as $currencyId => $each) {
            foreach ($each as $dimension => $val) {
                $tmp   = [];
                $tmp[] = Utils::getFromCache($currencyId, 'currencies')->name . (($dimension) ? ' - ' . $dimension : '');

                foreach ($val as $id => $field) $tmp[] = Utils::formatMoney($field, $currencyId);

                $summary[] = $tmp;
            }
        }

        dd($summary);*/

        return Excel::create($filename, function($excel) use($report, $data, $reportType, $format, $columns_labeled) {
            $excel->sheet(trans("texts.$reportType"), function($sheet) use($report, $data, $format, $columns_labeled) {

                $sheet->setOrientation('landscape');
                $sheet->freezeFirstRow();

                //Add border on PDF
                if($format == 'pdf')
                    $sheet->setAllBorders('thin');

                $sheet->rows(array_merge(
                    [array_map(function($col) {return $col['label'];}, $columns_labeled)],
                    $data
                ));

                //Styling header
                $sheet->cells('A1:'.Utils::num2alpha(count($columns_labeled)-1).'1', function($cells) {
                    $cells->setBackground('#777777');
                    $cells->setFontColor('#FFFFFF');
                    $cells->setFontSize(13);
                    $cells->setFontFamily('Calibri');
                    $cells->setFontWeight('bold');
                });


                $sheet->setAutoSize(true);
            });
        })->export($format);
    }
}
