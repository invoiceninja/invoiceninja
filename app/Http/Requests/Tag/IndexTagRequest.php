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

class IndexTagRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['sometimes', 'string', Rule::in(array_values(Tag::TAGGABLE_TYPES))],
        ];
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('entity_type', $input) && is_string($input['entity_type'])) {
            $input['entity_type'] = Tag::normalizeEntityType($input['entity_type']) ?? $input['entity_type'];
        }

        $this->replace($input);
    }
}
