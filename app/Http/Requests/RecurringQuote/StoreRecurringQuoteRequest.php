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
use App\Models\RecurringQuote;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;

class StoreRecurringQuoteRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() : bool
    {
        return auth()->user()->can('create', RecurringQuote::class);
    }

    public function rules()
    {
        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
            'client_id' => 'required|exists:clients,id,company_id,'.auth()->user()->company()->id,
        ];
    }

    protected function prepareForValidation()
    {
        $input = $this->all();

        if ($input['client_id']) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        //$input['line_items'] = json_encode($input['line_items']);
        $this->replace($input);
    }
}
