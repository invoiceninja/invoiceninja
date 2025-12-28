<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\TaskStatus;

use App\Http\Requests\Request;

class ActionTaskStatusRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        return [
            'ids' => 'required|bail|array',
            'action' => 'in:archive,restore,delete'
        ];
    }
}
