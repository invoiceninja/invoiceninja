<?php

namespace App\Jobs;

use Utils;
use Excel;
use App\Jobs\Job;

class ExportReportResults extends Job
{
    public function __construct($user, $format, $reportType, $params)
    {
        $this->user = $user;
        $this->format = strtolower($format);
        $this->reportType = $reportType;
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->user->hasPermission('view_reports')) {
            return false;
        }

        $format = $this->format;
        $reportType = $this->reportType;
        $params = $this->params;

        $data    = $params['displayData'];
        $columns = $params['columns'];
        $totals  = $params['reportTotals'];
        $report  = $params['report'];

        $filename = "{$params['startDate']}-{$params['endDate']}_invoiceninja-".strtolower(Utils::normalizeChars(trans("texts.$reportType")))."-report";

        $formats = ['csv', 'pdf', 'xlsx', 'zip'];
        if (! in_array($format, $formats)) {
            throw new \Exception("Invalid format request to export report");
        }

        //Get labeled header
        $data = array_merge(
            [
                array_map(function($col) {
                    return $col['label'];
                }, $report->tableHeaderArray())
            ],
            $data
        );

        $summary = [];
        if (count(array_values($totals))) {
            $summary[] = array_merge([
                trans("texts.totals")
            ], array_map(function ($key) {
                return trans("texts.{$key}");
            }, array_keys(array_values(array_values($totals)[0])[0])));
        }

        foreach ($totals as $currencyId => $each) {
            foreach ($each as $dimension => $val) {
                $tmp   = [];
                $tmp[] = Utils::getFromCache($currencyId, 'currencies')->name . (($dimension) ? ' - ' . $dimension : '');
                foreach ($val as $field => $value) {
                    if ($field == 'duration') {
                        $tmp[] = Utils::formatTime($value);
                    } else {
                        $tmp[] = Utils::formatMoney($value, $currencyId);
                    }
                }
                $summary[] = $tmp;
            }
        }

        return Excel::create($filename, function($excel) use($report, $data, $reportType, $format, $summary) {

            $excel->sheet(trans("texts.$reportType"), function($sheet) use($report, $data, $format, $summary) {
                $sheet->setOrientation('landscape');
                $sheet->freezeFirstRow();
                if ($format == 'pdf') {
                    $sheet->setAllBorders('thin');
                }

                if ($format == 'csv') {
                    $sheet->rows(array_merge($data, [[]], $summary));
                } else {
                    $sheet->rows($data);
                }

                // Styling header
                $sheet->cells('A1:'.Utils::num2alpha(count($data[0])-1).'1', function($cells) {
                    $cells->setBackground('#777777');
                    $cells->setFontColor('#FFFFFF');
                    $cells->setFontSize(13);
                    $cells->setFontFamily('Calibri');
                    $cells->setFontWeight('bold');
                });
                $sheet->setAutoSize(true);
            });

            if (count($summary)) {
                $excel->sheet(trans("texts.totals"), function($sheet) use($report, $summary, $format) {
                    $sheet->setOrientation('landscape');
                    $sheet->freezeFirstRow();

                    if ($format == 'pdf') {
                        $sheet->setAllBorders('thin');
                    }
                    $sheet->rows($summary);

                    // Styling header
                    $sheet->cells('A1:'.Utils::num2alpha(count($summary[0])-1).'1', function($cells) {
                        $cells->setBackground('#777777');
                        $cells->setFontColor('#FFFFFF');
                        $cells->setFontSize(13);
                        $cells->setFontFamily('Calibri');
                        $cells->setFontWeight('bold');
                    });
                    $sheet->setAutoSize(true);
                });
            }

        });
    }
}
