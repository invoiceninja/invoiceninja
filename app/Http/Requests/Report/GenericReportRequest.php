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

namespace App\Http\Requests\Report;

use App\Models\Design;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class GenericReportRequest extends Request
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'date_range' => 'bail|required|string',
            'end_date' => 'bail|required_if:date_range,custom|nullable|date',
            'start_date' => 'bail|required_if:date_range,custom|nullable|date',
            'report_keys' => 'present|array',
            'send_email' => 'required|bool',
            'document_email_attachment' => 'sometimes|bool',
            'pdf_email_attachment' => 'sometimes|bool',
            'include_deleted' => 'required|bool',
            'product_key' => 'sometimes|string|nullable',
            'template_id' => 'sometimes|string|nullable',
            // 'status' => 'sometimes|string|nullable|in:all,draft,sent,viewed,paid,unpaid,overdue',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator)
    {
        
        $validator->after(function ($validator) {

            if(!empty($this->template_id) && Design::where('id', $this->decodePrimaryKey($this->template_id))->where('is_template',true)->company()->doesntExist()) {
                $validator->errors()->add('template_id', 'Invalid Template ID Selected');
            }

        });

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

        $input['include_deleted'] = array_key_exists('include_deleted', $input) ? filter_var($input['include_deleted'], FILTER_VALIDATE_BOOLEAN) : false;

        $input['user_id'] = auth()->user()->id;

        if (!$this->checkAuthority()) {
            $input['product_key'] = '';
            $input['date_range'] = '';
            $input['start_date'] = '';
            $input['end_date'] = '';
            $input['send_email'] = true;
            $input['report_keys'] = [];
            $input['document_email_attachment'] = false;
            $input['pdf_email_attachment'] = false;
        }

        $this->replace($input);
    }

    private function checkAuthority()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin() || $user->hasPermission('view_reports');

    }
}
