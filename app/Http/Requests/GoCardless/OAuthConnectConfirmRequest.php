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

namespace App\Http\Requests\GoCardless;

use App\Libraries\MultiDB;
use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class OAuthConnectConfirmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Cache::has($this->query('state'));
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
        $data = Cache::get(
            key: $this->query('state'),
        );

        MultiDB::findAndSetDbByCompanyKey(
            company_key: $data['company_key'],
        );

        return Company::query()
            ->where('company_key', $data['company_key'])
            ->firstOrFail();
    }

}
