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

namespace App\Http\Requests\SystemLog;

use App\Http\Requests\Request;

class AdminSystemLogRequest extends Request
{

    public function authorize(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return $user->isAdmin() && $this->system_log->company_id == $user->company()->id;

    }


    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
