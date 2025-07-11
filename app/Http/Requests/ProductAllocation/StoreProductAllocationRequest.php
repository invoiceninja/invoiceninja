<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\ProductAllocation;

use App\Http\Requests\Request;
use App\Models\Product;
use App\Models\ProductAllocation;

class StoreProductAllocationRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', ProductAllocation::class);
    }

    public function rules()
    {
        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        $rules['company_id'] = 'required|numeric';
        $rules['product_id'] = 'required|numeric';
        $rules['client_id'] = 'sometimes|numeric';
        $rules['recurring_id'] = 'sometimes|numeric';
        $rules['project_id'] = 'sometimes|numeric';
        $rules['equipment_id'] = 'sometimes|string';
        $rules['subscription_id'] = 'sometimes|numeric';
        $rules['invoice_id'] = 'sometimes|numeric';

        $rules['quantity'] = 'sometimes|numeric';
        $rules['should_be_invoiced'] = 'sometimes|bool';
        $rules['invoice_aggregation_key'] = 'sometimes|string';

        $rules['from'] = 'sometimes|date';
        $rules['until'] = 'sometimes|date';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (array_key_exists('product_key', $input) && is_string($input['product_key'])) {
            $input['product_id'] = Product::where('product_key', $input['product_key'])->first()->id;
        }

        if (array_key_exists('company_id', $input) && is_string($input['company_id'])) {
            $input['company_id'] = $this->decodePrimaryKey($input['company_id']);
        }

        if (array_key_exists('recurring_id', $input) && is_string($input['recurring_id'])) {
            $input['recurring_id'] = $this->decodePrimaryKey($input['recurring_id']);
        }

        if (array_key_exists('product_id', $input) && is_string($input['product_id'])) {
            $input['product_id'] = $this->decodePrimaryKey($input['product_id']);
        }

        if (array_key_exists('equipment_id', $input) && is_string($input['equipment_id'])) {
            $input['equipment_id'] = $this->decodePrimaryKey($input['equipment_id']);
        }

        $this->replace($input);
    }
}
