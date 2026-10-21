<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Product;

use App\Http\Requests\Request;
use App\Models\Product;
use App\Utils\Traits\ChecksEntityStatus;

class UpdateProductRequest extends Request
{
    use ChecksEntityStatus;

    /** @var class-string */
    protected ?string $tag_entity_type = Product::class;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->product);
    }

    public function rules()
    {
        $rules = [];
        $rules['file'] = 'bail|sometimes|array';
        $rules['file.*'] = $this->fileValidation();
        $rules['documents'] = 'bail|sometimes|array';
        $rules['documents.*'] = $this->fileValidation();

        $rules['cost'] = 'numeric';
        $rules['price'] = 'numeric';
        $rules['quantity'] = 'numeric';
        $rules['in_stock_quantity'] = 'sometimes|numeric';
        $rules['stock_notification_threshold'] = 'sometimes|numeric';
        $rules['stock_notification'] = 'sometimes|bool';
        $rules['income_account_id'] = 'bail|sometimes|nullable|string|max:64';

        $rules['price_tiers'] = 'bail|sometimes|nullable|array';
        $rules['price_tiers.*.min_quantity'] = 'required|numeric|min:0';
        $rules['price_tiers.*.price'] = 'required_without:price_tiers.*.discount|numeric|min:0';
        $rules['price_tiers.*.discount'] = 'required_without:price_tiers.*.price|numeric|min:0|max:100';
        $rules['price_tiers_mode'] = 'bail|sometimes|string|in:all_units,progressive';

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if ($this->file('file') instanceof \Illuminate\Http\UploadedFile) {
            $this->files->set('file', [$this->file('file')]);
        }

        if (! isset($input['quantity'])) {
            $input['quantity'] = 1;
        }

        if (isset($input['documents'])) {
            unset($input['documents']);
        }

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['price_tiers']) && is_array($input['price_tiers'])) {
            $input['price_tiers'] = $this->sortPriceTiers($input['price_tiers']);
        }

        if (array_key_exists('in_stock_quantity', $input) && request()->has('update_in_stock_quantity') && request()->input('update_in_stock_quantity') == 'true') {
        } elseif (array_key_exists('in_stock_quantity', $input)) {
            unset($input['in_stock_quantity']);
        }

        foreach (['notes', 'product_key', 'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = \App\Services\Pdf\Purify::clean($input[$field], true);
            }
        }

        $this->replace($input);
    }
}
