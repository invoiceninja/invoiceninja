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

namespace App\Http\Requests\Tag;

use App\Http\Requests\Request;
use App\Models\Tag;
use Illuminate\Validation\Rule;

class StoreTagRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules(): array
    {
        $company_id = auth()->user()->companyId();

        return [
            'entity_type' => ['required', 'string', Rule::in(array_values(Tag::TAGGABLE_TYPES))],
            'name' => [
                'required',
                'string',
                'max:191',
                Rule::unique('tags', 'name')
                    ->where('company_id', $company_id)
                    ->where('entity_type', $this->input('entity_type')),
            ],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
        ];
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('entity_type', $input) && is_string($input['entity_type'])) {
            $input['entity_type'] = Tag::normalizeEntityType($input['entity_type']) ?? $input['entity_type'];
        }

        if (array_key_exists('color', $input) && $input['color'] === '') {
            $input['color'] = null;
        }

        $this->replace($input);
    }
}
