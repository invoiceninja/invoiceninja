<?php
/**
 * Quote Ninja (https://paymentninja.com).
 *
 * @link https://github.com/paymentninja/paymentninja source repository
 *
 * @copyright Copyright (c) 2021. Quote Ninja LLC (https://paymentninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\RecurringInvoice;

use App\Http\Requests\Request;

class UploadRecurringInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->recurring_invoice);
    }

    public function rules()
    {

    	$rules = [];

		if($this->input('documents'))
            $rules['documents'] = 'file|mimes:html,csv,png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx|max:2000000';

    	return $rules;

    }
}
