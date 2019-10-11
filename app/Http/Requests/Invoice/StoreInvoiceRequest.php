<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;

class StoreInvoiceRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Invoice::class);
    }

    public function rules()
    {
        $this->sanitize();

        return [
            'client_id' => 'required',
           // 'invoice_type_id' => 'integer',
      //      'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
        ];
    }

    public function sanitize()
    {
        $input = $this->all();

        $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        $input['line_items'] = isset($input['line_items']) ?: [];

        $this->replace($input);

        return $this->all();
    }
}

