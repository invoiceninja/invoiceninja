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

namespace App\Http\Requests\Quickbooks;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class AuthQuickbooksRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return is_array($this->getTokenContent());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            //
        ];
    }

    /**
     * Resolve one-time token instance.
     *
     * @return mixed
     */
    public function getTokenContent()
    {
        if ($this->state) {
            $this->token = $this->state;
        }

        $data = Cache::get($this->token);

        return $data;
    }

    public function getContact(): ?User
    {
        return User::findOrFail($this->getTokenContent()['user_id']);
    }

    public function getCompany(): ?Company
    {
        return Company::query()->where('company_key', $this->getTokenContent()['company_key'])->firstOrFail();
    }
}
