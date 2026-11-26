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

namespace App\Rules;

use App\Utils\Ninja;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WebhookTargetUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !filter_var($value, FILTER_VALIDATE_URL)) {
            $fail(ctrans('texts.invalid_url'));
            return;
        }

        $parts = parse_url($value);
        if (empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            $fail(ctrans('texts.invalid_url'));
            return;
        }

        $ip = gethostbyname($parts['host']);
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            $fail('Unable to resolve hostname.');
            return;
        }

        $flags = FILTER_FLAG_NO_RES_RANGE;
        if (!Ninja::isSelfHost() || !in_array($value, config('ninja.webhook_url_allowlist', []), true)) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, $flags)) {
            $fail(ctrans('texts.invalid_url'));
        }
    }
}
