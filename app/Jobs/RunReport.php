<?php

namespace App\Jobs;

use App;
use Str;
use Utils;
use Carbon;
use App\Jobs\Job;

class RunReport extends Job
{
    public function __construct($user, $reportType, $config, $isExport = false)
    {
        $this->user = $user;
        $this->reportType = $reportType;
        $this->config = $config;
        $this->isExport = $isExport;
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

        $reportType = $this->reportType;
        $config = $this->config;
        $config['subgroup'] = ! empty($config['subgroup']) ? $config['subgroup'] : false; // don't yet support charts in export

        $isExport = $this->isExport;
        $reportClass = '\\App\\Ninja\\Reports\\' . Str::studly($reportType) . 'Report';

        if (! empty($config['range'])) {
            switch ($config['range']) {
                case 'this_month':
                    $startDate = Carbon::now()->firstOfMonth()->toDateString();
                    $endDate = Carbon::now()->lastOfMonth()->toDateString();
                    break;
                case 'last_month':
                    $startDate = Carbon::now()->subMonth()->firstOfMonth()->toDateString();
                    $endDate = Carbon::now()->subMonth()->lastOfMonth()->toDateString();
                    break;
                case 'this_year':
                    $startDate = Carbon::now()->firstOfYear()->toDateString();
                    $endDate = Carbon::now()->lastOfYear()->toDateString();
                    break;
                case 'last_year':
                    $startDate = Carbon::now()->subYear()->firstOfYear()->toDateString();
                    $endDate = Carbon::now()->subYear()->lastOfYear()->toDateString();
                    break;
            }
        } elseif (! empty($config['start_date_offset'])) {
            $startDate = Carbon::now()->subDays($config['start_date_offset'])->toDateString();
            $endDate = Carbon::now()->subDays($config['end_date_offset'])->toDateString();
        } else {
            $startDate = $config['start_date'];
            $endDate = $config['end_date'];
        }

        $report = new $reportClass($startDate, $endDate, $isExport, $config);
        $report->run();

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'report' => $report,
        ];

        $report->exportParams = array_merge($params, $report->results());

        return $report;
    }
}
