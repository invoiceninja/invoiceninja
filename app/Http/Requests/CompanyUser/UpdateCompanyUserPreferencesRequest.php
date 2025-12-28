<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\CompanyUser;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class UpdateCompanyUserPreferencesRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->id == $this->user->id;
    }

    public function rules()
    {
        return [
            'react_settings' => 'required'
        ];
    }
}
