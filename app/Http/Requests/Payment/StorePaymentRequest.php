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

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;

class StorePaymentRequest extends Request
{
    use MakesHash;
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize() : bool
    {
        return auth()->user()->can('create', Payment::class);
    }

    public function rules()
    {
        $this->sanitize();

        return [
            'documents' => 'mimes:png,ai,svg,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx',
            'client_id' => 'integer|nullable',
            'payment_type_id' => 'integer|nullable',
            'amount' => 'numeric',
            'payment_date' => 'required',
        ];
    }


    public function sanitize()
    {
        
        $input = $this->all();

        if(isset($input['invoices']))
            $input['invoices'] = $this->transformKeys(array_column($input['invoices']), 'id');

        $this->replace($input);

        return $this->all();
    }

    public function messages()
    {

    }


}

