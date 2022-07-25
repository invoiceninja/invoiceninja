<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\PurchaseOrder;


use App\Http\Requests\Request;
use App\Models\PurchaseOrder;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StorePurchaseOrderRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return auth()->user()->can('create', PurchaseOrder::class);
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [];

        $rules['vendor_id'] = 'bail|required|exists:vendors,id,company_id,'.auth()->user()->company()->id.',is_deleted,0';

        $rules['number'] = ['nullable', Rule::unique('purchase_orders')->where('company_id', auth()->user()->company()->id)];
        $rules['discount']  = 'sometimes|numeric';
        $rules['is_amount_discount'] = ['boolean'];
        $rules['line_items'] = 'array';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['line_items']) && is_array($input['line_items'])) 
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];

        $input['amount'] = 0;
        $input['balance'] = 0;

        $this->replace($input);
    }

}
