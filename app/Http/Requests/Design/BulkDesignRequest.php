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

namespace App\Http\Requests\Design;

use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class BulkDesignRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company_id = $user->company()->id;

        $exists = Rule::exists('designs', 'id')->where('company_id', $company_id);

        if ($this->input('action') === 'clone') {
            $exists = Rule::exists('designs', 'id')->where(function ($query) use ($company_id) {
                $query->where(function ($q) use ($company_id) {
                    $q->where('company_id', $company_id)
                        ->orWhereNull('company_id');
                });
            });
        }

        return [
            'action' => ['required', 'bail', 'in:archive,restore,delete,clone'],
            'ids' => [
                'required',
                'bail',
                'array',
                'min:1',
                $exists,
            ],
            'ids.*' => ['bail', 'integer'],
        ];
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (isset($input['ids']) && is_array($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        $this->replace($input);
    }
}
