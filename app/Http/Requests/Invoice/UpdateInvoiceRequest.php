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
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends Request
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {

        return auth()->user()->can('edit', $this->invoice);

    }


    public function rules()
    {

        $this->sanitize();

        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
            //'client_id' => 'required|integer',
            //'invoice_type_id' => 'integer',
        ];
    }

    public function sanitize()
    {
        $input = $this->all();

        if(isset($input['client_id']))
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);

        $this->replace($input);

        return $this->all();
    }
}