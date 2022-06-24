<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Chart;

use App\Http\Requests\Request;
use App\Models\Activity;

class ShowChartRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! array_key_exists('start_date', $input)) {
            $input['start_date'] = now()->subDays(20);
        }

        if (! array_key_exists('end_date', $input)) {
            $input['end_date'] = now();
        }

        $this->replace($input);
    }
}
