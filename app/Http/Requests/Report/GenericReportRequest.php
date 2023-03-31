<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Report;

use App\Http\Requests\Request;

class GenericReportRequest extends Request
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
            'date_range' => 'bail|required|string',
            'end_date' => 'bail|required_if:date_range,custom|nullable|date',
            'start_date' => 'bail|required_if:date_range,custom|nullable|date',
            'report_keys' => 'present|array',
            'send_email' => 'required|bool',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! array_key_exists('date_range', $input) || $input['date_range'] == '') {
            $input['date_range'] = 'all';
        }

        if (! array_key_exists('report_keys', $input)) {
            $input['report_keys'] = [];
        }

        if (! array_key_exists('send_email', $input)) {
            $input['send_email'] = true;
        }

        if (array_key_exists('date_range', $input) && $input['date_range'] != 'custom') {
            $input['start_date'] = null;
            $input['end_date'] = null;
        }

        $this->replace($input);
    }
}
