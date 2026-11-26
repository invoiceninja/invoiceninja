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

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;
use App\Models\Design;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkClientRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'action' => 'required|string|in:archive,restore,delete,template,assign_group,bulk_update,clone',
            'ids' => ['required','bail','array',Rule::exists('clients', 'id')->where('company_id', $user->company()->id)],
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string|required_if:action,template',
            'group_settings_id' => ['required_if:action,assign_group',Rule::exists('group_settings', 'id')->where('company_id', $user->company()->id)],
            'send_email' => 'sometimes|bool',
            'column' => ['required_if:action,bulk_update', 'string', Rule::in(\App\Models\Client::$bulk_update_columns)],
            'new_value' => ['required_if:action,bulk_update|string'],
        ];

    }

    public function withValidator(Validator $validator)
    {
        if ($validator->errors()->isNotEmpty() || $this->action != 'template') {
            return;
        }

        $validator->after(function ($validator) {
            $user = auth()->user();

            if (Design::withTrashed()->where('company_id', $user->company()->id)->where('id', $this->decodePrimaryKey($this->template_id))->doesntExist()) {
                $validator->errors()->add('template_id', 'Template not found');
            }
            
        });
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        if (isset($input['group_settings_id'])) {
            $input['group_settings_id'] = $this->decodePrimaryKey($input['group_settings_id']);
        }

        $this->replace($input);
    }
}
