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
use App\Http\ValidationRules\ValidPayableInvoicesRule;
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

        $rules = [
            'client_id' => 'required',
            'invoices' => 'present|array',
            'invoices' => new ValidPayableInvoicesRule(),
        ];

        return $rules;
            
    }


    public function sanitize()
    {
        $input = $this->all();
                
        if(isset($input['client_id']))
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);

        if(isset($input['invoices']))
            $input['invoices'] = $this->transformKeys(array_column($input['invoices'],'id'));

        $this->replace($input);   

        return $this->all();

    }


}