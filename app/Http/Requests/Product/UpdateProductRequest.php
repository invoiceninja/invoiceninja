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

namespace App\Http\Requests\Product;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;

class UpdateProductRequest extends Request
{
    use ChecksEntityStatus;

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
        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->file_validation;
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->file_validation;
        }else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->file_validation;
        } elseif ($this->file('file')) {
            $rules['file'] = $this->file_validation;
        }

        $rules['cost'] = 'numeric';
        $rules['price'] = 'numeric';
        $rules['quantity'] = 'numeric';
        $rules['in_stock_quantity'] = 'sometimes|numeric';
        $rules['stock_notification_threshold'] = 'sometimes|numeric';
        $rules['stock_notification'] = 'sometimes|bool';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! isset($input['quantity'])) {
            $input['quantity'] = 1;
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('in_stock_quantity', $input) && request()->has('update_in_stock_quantity') && request()->input('update_in_stock_quantity') == 'true') {
        } elseif (array_key_exists('in_stock_quantity', $input)) {
            unset($input['in_stock_quantity']);
        }

        $this->replace($input);
    }
}
