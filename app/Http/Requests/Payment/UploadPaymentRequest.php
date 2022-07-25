<?php
/**
 * Payment Ninja (https://paymentninja.com).
 *
 * @link https://github.com/paymentninja/paymentninja source repository
 *
 * @copyright Copyright (c) 2022. Payment Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;

class UploadPaymentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->payment);
    }

    public function rules()
    {
        $rules = [];

        if ($this->input('documents')) {
            $rules['documents'] = 'file|mimes:csv,png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:2000000';
        }

        return $rules;
    }
}
