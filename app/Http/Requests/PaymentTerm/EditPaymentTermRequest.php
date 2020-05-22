<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Requests\PaymentTerm;

use App\Http\Requests\Request;
use App\Models\PaymentTerm;

class EditPaymentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */

    public function authorize()
    {
        return auth()->user()->can('edit', $this->payment_terms);
    }

    public function rules()
    {
        $rules = [];
        
        return $rules;
    }


    protected function prepareForValidation()
    {
        $input = $this->all();

        //$input['id'] = $this->encodePrimaryKey($input['id']);

        $this->replace($input);
    }
}
