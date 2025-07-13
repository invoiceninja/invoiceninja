<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Carbon\Carbon;

class ValidAggregationIntervalRule implements Rule
{
    protected $message;

    public function passes($attribute, $value)
    {
        $allowed = ['hourly', 'daily', 'weekly', 'monthly', 'yearly'];

        if (in_array($value, $allowed)) {
            return true;
        }

        if (is_numeric($value)) {
            return true;
        }

        // Optional: support date formats like "Y-m-d", "Y-m", etc.
        try {
            Carbon::now()->format($value); // Not enough: we want to validate the format
            Carbon::createFromFormat($value, Carbon::now()->format($value));
            return true;
        } catch (\Exception $e) {
            $this->message = 'The :attribute is not a valid aggregation interval or date format.';
            return false;
        }
    }

    public function message()
    {
        return $this->message ?? 'The :attribute must be one of: hourly, daily, weekly, monthly, yearly, a number (seconds), or a valid date format.';
    }
}
