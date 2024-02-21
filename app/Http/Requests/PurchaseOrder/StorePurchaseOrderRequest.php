<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', PurchaseOrder::class);
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        $rules['vendor_id'] = 'bail|required|exists:vendors,id,company_id,'.$user->company()->id.',is_deleted,0';

        $rules['number'] = ['nullable', Rule::unique('purchase_orders')->where('company_id', $user->company()->id)];
        $rules['discount']  = 'sometimes|numeric';
        $rules['is_amount_discount'] = ['boolean'];
        $rules['line_items'] = 'array';

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
        }

        $rules['status_id'] = 'nullable|integer|in:1,2,3,4,5';
        $rules['exchange_rate'] = 'bail|sometimes|numeric';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['line_items']) && is_array($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        }

        $input['amount'] = 0;
        $input['balance'] = 0;

        if (array_key_exists('exchange_rate', $input) && is_null($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        $this->replace($input);
    }
}
