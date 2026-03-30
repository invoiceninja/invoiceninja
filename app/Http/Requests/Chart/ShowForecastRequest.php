<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Chart;

class ShowForecastRequest extends ShowChartRequest
{
    public function rules()
    {
        return array_merge(parent::rules(), [
            'bucket_type' => 'bail|sometimes|string|in:daily,weekly,monthly',
        ]);
    }

    public function prepareForValidation()
    {
        /** @var \App\Models\User auth()->user */
        $user = auth()->user();

        $input = $this->all();

        $input['include_drafts'] = filter_var($input['include_drafts'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (isset($input['date_range'])) {
            $dates = $this->calculateStartAndEndDates($input, $user->company());
            $input['start_date'] = $dates[0];
            $input['end_date'] = $dates[1];
        }

        if (! isset($input['start_date'])) {
            $input['start_date'] = now()->format('Y-m-d');
        }

        if (! isset($input['end_date'])) {
            $input['end_date'] = now()->addMonths(6)->format('Y-m-d');
        }

        if (! isset($input['bucket_type'])) {
            $input['bucket_type'] = 'monthly';
        }

        $this->replace($input);
    }
}
