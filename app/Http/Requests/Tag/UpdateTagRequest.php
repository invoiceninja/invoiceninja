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
use Illuminate\Validation\Rule;

class UpdateTagRequest extends Request
{
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->tag);
    }

    public function rules(): array
    {
        /** @var \App\Models\Tag $tag */
        $tag = $this->tag;

        return [
            'name' => [
                'sometimes',
                'string',
                'max:191',
                Rule::unique('tags', 'name')
                    ->where('company_id', $tag->company_id)
                    ->where('entity_type', $tag->entity_type)
                    ->ignore($tag->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
        ];
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        unset($input['entity_type']);

        if (array_key_exists('color', $input) && $input['color'] === '') {
            $input['color'] = null;
        }

        $this->replace($input);
    }
}
