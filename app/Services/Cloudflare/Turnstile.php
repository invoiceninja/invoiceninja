<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Cloudflare;

use Illuminate\Support\Facades\Http;

class Turnstile
{
    public string $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        public readonly string $field = 'cf-turnstile',
    ) {
    }

    public function authorize(): bool
    {
        $response = Http::asForm()->post($this->url, [
            'response' => request()->get($this->field),
            'secret' => config('ninja.cloudflare.turnstile.secret'),
            'remoteip' => request()->ip(),
        ]);

        if ($response->ok()) {
            $outcome = $response->json();

            if ($outcome['success']) {
                return true;
            }
        }

        return false;
    }
}
