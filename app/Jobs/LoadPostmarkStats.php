<?php

namespace App\Jobs;

use App\Jobs\Job;
use Postmark\PostmarkClient;
use stdClass;
use DateInterval;
use DatePeriod;

class LoadPostmarkStats extends Job
{
    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;

        $this->response = new stdClass();
        $this->postmark = new \Postmark\PostmarkClient(config('services.postmark'));
        $this->account = auth()->user()->account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! auth()->user()->hasPermission('view_reports')) {
            return $this->response;
        }

        $this->loadOverallStats();
        $this->loadSentStats();
        $this->loadPlatformStats();
        $this->loadEmailClientStats();

        return $this->response;
    }

    private function loadOverallStats() {
        $startDate = date_create($this->startDate);
        $endDate = date_create($this->endDate);

        $eventTypes = ['sent', 'opened'];

        foreach ($eventTypes as $eventType) {
            $data = [];
            $endDate->modify('+1 day');
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($startDate, $interval, $endDate);
            $endDate->modify('-1 day');
            $records = [];

            if ($eventType == 'sent') {
                $response = $this->postmark->getOutboundSendStatistics($this->account->account_key, request()->start_date, request()->end_date);
            } else {
                $response = $this->postmark->getOutboundOpenStatistics($this->account->account_key, request()->start_date, request()->end_date);
            }

            foreach ($response->days as $key => $val) {
                $field = $eventType == 'opened' ? 'unique' : $eventType;
                $data[$val['date']] = $val[$field];
            }

            foreach ($period as $day) {
                $date = $day->format('Y-m-d');
                $records[] = isset($data[$date]) ? $data[$date] : 0;

                if ($eventType == 'sent') {
                    $labels[] = $day->format('m/d/Y');
                }
            }

            if ($eventType == 'sent') {
                $color = '51,122,183';
            } elseif ($eventType == 'opened') {
                $color = '54,193,87';
            } elseif ($eventType == 'bounced') {
                $color = '128,128,128';
            }

            $group = new stdClass();
            $group->data = $records;
            $group->label = trans("texts.{$eventType}");
            $group->lineTension = 0;
            $group->borderWidth = 4;
            $group->borderColor = "rgba({$color}, 1)";
            $group->backgroundColor = "rgba({$color}, 0.1)";
            $datasets[] = $group;
        }

        $data = new stdClass();
        $data->labels = $labels;
        $data->datasets = $datasets;
        $this->response->data = $data;
    }

    private function loadSentStats() {
        $account = $this->account;
        $data = $this->postmark->getOutboundOverviewStatistics($this->account->account_key, request()->start_date, request()->end_date);
        $percent = $data->sent ? ($data->uniqueopens / $data->sent * 100) : 0;
        $this->response->totals = [
            'sent' => $account->formatNumber($data->sent),
            'opened' => sprintf('%s | %s%%', $account->formatNumber($data->uniqueopens), $account->formatNumber($percent)),
            'bounced' => sprintf('%s | %s%%', $account->formatNumber($data->bounced), $account->formatNumber($data->bouncerate, 3)),
            //'spam' => sprintf('%s | %s%%', $account->formatNumber($data->spamcomplaints), $account->formatNumber($data->spamcomplaintsrate, 3))
        ];
    }

    private function loadPlatformStats() {
        $data = $this->postmark->getOutboundPlatformStatistics($this->account->account_key, request()->start_date, request()->end_date);
        $account = $this->account;
        $str = '';
        $total = 0;

        $total = $data['desktop'] + $data['mobile'] + $data['webmail'];

        foreach (['mobile', 'desktop', 'webmail'] as $platform) {
            $percent = $total ? ($data[$platform] / $total * 100) : 0;
            $str .= sprintf('<tr><td>%s</td><td>%s%%</td></tr>', trans('texts.' . $platform), $account->formatNumber($percent));
        }

        $this->response->platforms = $str;
    }

    private function loadEmailClientStats() {
        $data = $this->postmark->getOutboundEmailClientStatistics($this->account->account_key, request()->start_date, request()->end_date);
        $account = $this->account;
        $str = '';
        $total = 0;
        $clients = [];

        foreach ($data as $key => $val) {
            if ($key == 'days') {
                continue;
            }

            $total += $val;
            $clients[$key] = $val;
        }

        arsort($clients);

        foreach ($clients as $key => $val) {
            $percent = $total ? ($val / $total * 100) : 0;
            if ($percent < 0.5) {
                continue;
            }
            $str .= sprintf('<tr><td>%s</td><td>%s%%</td></tr>', ucwords($key), $account->formatNumber($percent));
        }

        $this->response->emailClients = $str;
    }

}
