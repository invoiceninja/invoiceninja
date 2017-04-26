<?php

namespace App\Ninja\Reports;

use Auth;

class AbstractReport
{
    public $startDate;
    public $endDate;
    public $isExport;
    public $options;

    public $totals = [];
    public $columns = [];
    public $data = [];

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

    public function results()
    {
        return [
            'columns' => $this->columns,
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

    public function tableHeader()
    {
        $str = '';

        foreach ($this->columns as $key => $val) {
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
            }

            $class = count($class) ? implode(' ', $class) : 'group-false';
            $label = trans("texts.{$field}");
            $str .= "<th class=\"{$class}\">{$label}</th>";
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
}
