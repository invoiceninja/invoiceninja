<?php

namespace App\Ninja\Reports;

use Auth;
use DateInterval;
use DatePeriod;
use stdClass;

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
                $class[] = 'group-date-' . (isset($this->options['group_dates_by']) ? $this->options['group_dates_by'] : 'monthyear');
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
        $groupBy = empty($this->options['group_dates_by']) ? 'day' : $this->options['group_dates_by'];

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

    public function getChartData()
    {
        $startDate = date_create($this->startDate);
        $endDate = date_create($this->endDate);
        $groupBy = $this->chartGroupBy();

        /*
        if ($groupBy == 'DAY') {
            $groupBy = 'DAYOFYEAR';
        }
        */

        $datasets = [];
        $labels = [];
        $totals = new stdClass();

        foreach ($this->chartData as $dimension => $data) {
            $endDate->modify('+1 '.$groupBy);
            $interval = new DateInterval('P1'.substr($groupBy, 0, 1));
            $period = new DatePeriod($startDate, $interval, $endDate);
            $endDate->modify('-1 '.$groupBy);
            $records = [];

            foreach ($period as $date) {
                $labels[] = $date->format('m/d/Y');
                /*
                if ($entityType == ENTITY_INVOICE) {
                    $labels[] = $d->format('m/d/Y');
                }
                */

                $date = $this->formatDate($date);
                $records[] = isset($data[$date]) ? $data[$date] : 0;
            }

            $color = '51,122,183';
            /*
            if ($entityType == ENTITY_INVOICE) {
                $color = '51,122,183';
            } elseif ($entityType == ENTITY_PAYMENT) {
                $color = '54,193,87';
            } elseif ($entityType == ENTITY_EXPENSE) {
                $color = '128,128,128';
            }
            */

            $record = new stdClass();
            $record->data = $records;
            $record->label = trans("texts.{$dimension}");
            $record->lineTension = 0;
            $record->borderWidth = 4;
            $record->borderColor = "rgba({$color}, 1)";
            $record->backgroundColor = "rgba({$color}, 0.1)";
            $datasets[] = $record;
        }

        $data = new stdClass();
        $data->labels = $labels;
        $data->datasets = $datasets;

        return $data;
    }
}
