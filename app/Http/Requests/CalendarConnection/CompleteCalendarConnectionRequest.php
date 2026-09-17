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

use Illuminate\Foundation\Http\FormRequest;

class CompleteCalendarConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'handoff' => ['required_without_all:state,code', 'string', 'size:64'],
            'state' => ['required_without:handoff', 'string', 'size:64'],
            'code'  => ['required_without:handoff', 'string', 'max:2048'],
        ];
    }
}
