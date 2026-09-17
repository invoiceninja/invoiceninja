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

namespace App\Http\Requests\Client;

use App\Http\Requests\Request;
use App\Utils\Ninja;
use Illuminate\Validation\Validator;

class PurgeClientRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin() && $user->can('edit', $this->client);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! Ninja::isHosted()) {
                return;
            }

            /** @var \App\Models\User $user */
            $user = auth()->user();

            if ((bool) $user->company()->getSetting('france_reporting_enabled')) {
                $validator->errors()->add(
                    'client',
                    'The client cannot be purged while France reporting is enabled.',
                );
            }
        });
    }
}
