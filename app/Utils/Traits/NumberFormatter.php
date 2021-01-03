<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Utils\Traits;

/**
 * Class NumberFormatter.
 */
trait NumberFormatter
{
    private function formatValue($value, $precision) : string
    {
        return number_format($this->parseFloat($value), $precision, '.', '');
    }

    /**
     * Parse a float value that may be delimited with either a comma or decimal point.
     *
     * @param      string $value  The value
     *
     * @return     float   Consumable float value
     */
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
