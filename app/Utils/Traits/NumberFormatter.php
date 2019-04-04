<?php

namespace App\Utils\Traits;

/**
 * Class NumberFormatter
 * @package App\Utils\Traits
 */
trait NumberFormatter
{
	private function formatValue($value) : string
	{
        return number_format($this->parseFloat($value), $this->precision, '.', '');
	}

	private function parseFloat($value) : float
    {
        // check for comma as decimal separator
        if (preg_match('/,[\d]{1,2}$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9\.\-]/', '', $value);

        return floatval($value);
    }

}