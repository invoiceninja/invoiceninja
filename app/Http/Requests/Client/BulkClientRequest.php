<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

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
            'action' => 'required|string|in:archive,restore,delete,template,assign_group,bulk_update',
            'ids' => ['required','bail','array',Rule::exists('clients', 'id')->where('company_id', $user->company()->id)],
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'group_settings_id' => ['required_if:action,assign_group',Rule::exists('group_settings', 'id')->where('company_id', $user->company()->id)],
            'send_email' => 'sometimes|bool',
            'column' => ['required_if:action,bulk_update', 'string', Rule::in(\App\Models\Client::$bulk_update_columns)],
            'new_value' => ['required_if:action,bulk_update|string'],
        ];

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
