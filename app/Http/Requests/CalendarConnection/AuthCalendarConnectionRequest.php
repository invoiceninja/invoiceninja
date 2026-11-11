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

namespace App\Http\Requests\CalendarConnection;

use App\DataMapper\Referral\CalendarConnection;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;

class AuthCalendarConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $content = $this->getTokenContent();

        if (!is_array($content) || empty($content['user_id']) || empty($content['company_key'])) {
            return false;
        }

        $expected = match ($this->route('provider')) {
            CalendarConnection::PROVIDER_GOOGLE    => 'calendar_google',
            CalendarConnection::PROVIDER_MICROSOFT => 'calendar_microsoft',
            default => null,
        };

        return $expected !== null && ($content['context'] ?? null) === $expected;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTokenContent(): ?array
    {
        $data = Cache::get($this->route('hash'));

        return is_array($data) ? $data : null;
    }

    public function getCompany(): Company
    {
        return Company::query()
            ->where('company_key', $this->getTokenContent()['company_key'])
            ->firstOrFail();
    }

    public function resolveUser(): User
    {
        return User::query()->findOrFail($this->getTokenContent()['user_id']);
    }
}
