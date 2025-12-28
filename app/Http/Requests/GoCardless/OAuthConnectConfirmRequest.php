<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\GoCardless;

use App\Libraries\MultiDB;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;

class OAuthConnectConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'state' => ['required', 'string'],
            'code' => ['required','string'],
        ];
    }

    public function getCompany(): \Illuminate\Database\Eloquent\Model|\Illuminate\Database\Eloquent\Builder|\App\Models\BaseModel
    {
        MultiDB::findAndSetDbByCompanyKey(
            $this->query('state'),
        );

        return Company::query()
            ->where('company_key', $this->query('state'))
            ->firstOrFail();
    }
}
