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

namespace App\Http\Requests\ProductEquipment;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use Illuminate\Validation\Rule;

class UpdateProductEquipmentRequest extends Request
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

        return $user->can('edit', $this->product_equipment);
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['file'] = 'bail|sometimes|array';
        $rules['file.*'] = $this->fileValidation();

        $rules['serial_number'] = 'sometimes|string';
        $rules['client_id'] = ['sometimes', 'bail', Rule::exists('clients', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if ($this->file('file') instanceof \Illuminate\Http\UploadedFile) {
            $this->files->set('file', [$this->file('file')]);
        }

        if (!isset($input['quantity'])) {
            $input['quantity'] = 1;
        }

        if (isset($input['documents'])) {
            unset($input['documents']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        $this->replace($input);
    }
}
