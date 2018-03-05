<?php

namespace App\Ninja\Reports;

use Utils;
use Auth;
use Carbon;
use DateInterval;
use DatePeriod;
use stdClass;
use App\Models\Client;

class AbstractReport
{
    public $startDate;
    public $endDate;
    public $isExport;
    public $options;

    public $totals = [];
    public $data = [];
    public $chartData = [];

    public function __construct($startDate, $endDate, $isExport, $options = false)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->isExport = $isExport;
        $this->options = $options;
    }

    public function run()
    {

    }

    public function getColumns()
    {
        return [];
    }

    public function results()
    {
        return [
            'columns' => $this->getColumns(),
            'displayData' => $this->data,
            'reportTotals' => $this->totals,
        ];
    }

    protected function addToTotals($currencyId, $field, $value, $dimension = false)
    {
        $currencyId = $currencyId ?: Auth::user()->account->getCurrencyId();

        if (! isset($this->totals[$currencyId][$dimension])) {
            $this->totals[$currencyId][$dimension] = [];
        }

        if (! isset($this->totals[$currencyId][$dimension][$field])) {
            $this->totals[$currencyId][$dimension][$field] = 0;
        }

        $this->totals[$currencyId][$dimension][$field] += $value;
    }

    public function tableHeaderArray() {
        $columns_labeled = [];

        foreach ($this->getColumns() as $key => $val) {
            if (is_array($val)) {
                $field = $key;
                $class = $val;
            } else {
                $field = $val;
                $class = [];
            }

            if (strpos($field, 'date') !== false) {
                $class[] = 'group-date-' . (isset($this->options['group']) ? $this->options['group'] : 'monthyear');
            } elseif (in_array($field, ['client', 'vendor', 'product', 'user', 'method', 'category', 'project'])) {
                $class[] = 'group-letter-100';
            } elseif (in_array($field, ['amount', 'paid', 'balance'])) {
                $class[] = 'group-number-50';
            } elseif (in_array($field, ['age'])) {
                $class[] = 'group-number-30';
            }

            if (! in_array('custom', $class)) {
                $label = trans("texts.{$field}");
            } else {
                $label = $field;
            }
            $class = count($class) ? implode(' ', $class) : 'group-false';

            $columns_labeled[] = [
                'label' => $label,
                'class' => $class,
                'key' => $field
            ];
        }

        return $columns_labeled;
    }

    public function tableHeader()
    {
        $columns_labeled = $this->tableHeaderArray();
        $str = '';

        foreach ($columns_labeled as $field => $attr) {
            $str .= sprintf('<th class="%s" data-priorityx="3">%s</th>', $attr['class'], $attr['label']);
        }

        return $str;
    }

    // convert the date format to one supported by tablesorter
    public function convertDateFormat()
    {
        $account = Auth::user()->account;
        $format = $account->getMomentDateFormat();
        $format = strtolower($format);
        $format = str_replace('do', '', $format);

        $orignalFormat = $format;
        $format = preg_replace("/[^mdy]/", '', $format);

        $lastLetter = false;
        $reportParts = [];
        $phpParts = [];

        foreach (str_split($format) as $letter) {
            if ($lastLetter && $letter == $lastLetter) {
                continue;
            }
            $lastLetter = $letter;
            if ($letter == 'm') {
                $reportParts[] = 'mm';
                $phpParts[] = 'm';
            } elseif ($letter == 'd') {
                $reportParts[] = 'dd';
                $phpParts[] = 'd';
            } elseif ($letter == 'y') {
                $reportParts[] = 'yyyy';
                $phpParts[] = 'Y';
            }
        }

        return join('', $reportParts);
    }

    protected function getDimension($entity)
    {
        $subgroup = $this->options['subgroup'];

        if ($subgroup == 'user') {
            return $entity->user->getDisplayName();
        } elseif ($subgroup == 'client') {
            if ($entity instanceof Client) {
                return $entity->getDisplayName();
            } elseif ($entity->client) {
                return $entity->client->getDisplayName();
            } else {
                return trans('texts.unset');
            }
        }
    }

    protected function addChartData($dimension, $date, $amount)
    {
        if (! isset($this->chartData[$dimension])) {
            $this->chartData[$dimension] = [];
        }

        $date = $this->formatDate($date);

        if (! isset($this->chartData[$dimension][$date])) {
            $this->chartData[$dimension][$date] = 0;
        }

        $this->chartData[$dimension][$date] += $amount;
    }

    public function chartGroupBy()
    {
        $groupBy = empty($this->options['group']) ? 'day' : $this->options['group'];

        if ($groupBy == 'monthyear') {
            $groupBy = 'month';
        }

        return strtoupper($groupBy);
    }

    protected function formatDate($date)
    {
        if (! $date instanceof \DateTime) {
            $date = new \DateTime($date);
        }

        $groupBy = $this->chartGroupBy();
        $dateFormat = $groupBy == 'DAY' ? 'z' : ($groupBy == 'MONTH' ? 'm' : '');

        return $date->format('Y' . $dateFormat);
    }

    public function getLineChartData()
    {
        $startDate = date_create($this->startDate);
        $endDate = date_create($this->endDate);
        $groupBy = $this->chartGroupBy();

        $datasets = [];
        $labels = [];

        foreach ($this->chartData as $dimension => $data) {
            $interval = new DateInterval('P1'.substr($groupBy, 0, 1));
            $intervalStartDate = Carbon::instance($startDate);
            $intervalEndDate = Carbon::instance($endDate);

            // round dates to match grouping
            $intervalStartDate->hour(0)->minute(0)->second(0);
            $intervalEndDate->hour(24)->minute(0)->second(0);
            if ($groupBy == 'MONTHYEAR' || $groupBy == 'YEAR') {
                $intervalStartDate->day(1);
                $intervalEndDate->addMonth(1)->day(1);
            }
            if ($groupBy == 'YEAR') {
                $intervalStartDate->month(1);
                $intervalEndDate->month(12);
            }

            $period = new DatePeriod($intervalStartDate, $interval, $intervalEndDate);
            $records = [];

            foreach ($period as $date) {
                $labels[] = $date->format('m/d/Y');
                $date = $this->formatDate($date);
                $records[] = isset($data[$date]) ? $data[$date] : 0;
            }

            $record = new stdClass();
            $datasets[] = $record;
            $color = Utils::brewerColorRGB(count($datasets));

            $record->data = $records;
            $record->label = $dimension;
            $record->lineTension = 0;
            $record->borderWidth = 3;
            $record->borderColor = "rgba({$color}, 1)";
            $record->backgroundColor = "rgba(255,255,255,0)";
        }

        $data = new stdClass();
        $data->labels = $labels;
        $data->datasets = $datasets;

        return $data;
    }

    public function isLineChartEnabled()
    {
        return $this->options['group'];
    }

    public function isPieChartEnabled()
    {
        return $this->options['subgroup'];
    }

    public function getPieChartData()
    {
        if (! $this->isPieChartEnabled()) {
            return false;
        }

        $datasets = [];
        $labels = [];
        $totals = [];

        foreach ($this->chartData as $dimension => $data) {
            foreach ($data as $date => $value) {
                if (! isset($totals[$dimension])) {
                    $totals[$dimension] = 0;
                }

                $totals[$dimension] += $value;
            }
        }

        $response = new stdClass();
        $response->labels = [];

        $datasets = new stdClass();
        $datasets->data = [];
        $datasets->backgroundColor = [];

        foreach ($totals as $dimension => $value) {
            $response->labels[] = $dimension;
            $datasets->data[] = $value;
            $datasets->lineTension = 0;
            $datasets->borderWidth = 3;

            $color = count($totals) ? Utils::brewerColorRGB(count($response->labels)) : '51,122,183';
            $datasets->borderColor[] = "rgba({$color}, 1)";
            $datasets->backgroundColor[] = "rgba({$color}, 0.1)";
        }

        $response->datasets = [$datasets];

        return $response;
    }
}
