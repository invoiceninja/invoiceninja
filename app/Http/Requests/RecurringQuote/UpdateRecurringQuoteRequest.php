<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\RecurringQuote;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateRecurringQuoteRequest extends Request
{
    use ChecksEntityStatus;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('edit', $this->recurring_quote);
    }

    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];

        $this->replace($input);
    }
}
