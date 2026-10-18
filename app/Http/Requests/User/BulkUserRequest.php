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

namespace App\Http\Requests\User;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Ninja\CanRestoreUserRule;
use App\Utils\Ninja;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;

class BulkUserRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** Guards against a user deleting themselves */
        if ($this->action == 'delete' && in_array(auth()->user()->id, $this->input('ids', []))) {
            return false;
        }

        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $rules = [
            'action' => ['required', 'bail', 'in:archive,restore,delete'],
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', Rule::exists('users', 'id')->where('account_id', auth()->user()->company()->account_id)],
        ];

        if (Ninja::isHosted() && $this->action && $this->action == 'restore') {
            $rules['ids'] = new CanRestoreUserRule();
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input['ids'] = $this->transformKeys($input['ids']);

        $this->replace($input);
    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException("This Action is unauthorized.");
    }
}
