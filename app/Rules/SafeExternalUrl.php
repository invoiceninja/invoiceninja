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

/**
 * SSRF shape check for hosted-mode URL validation.
 *
 * Four constraints:
 *   - scheme must be https
 *   - no userinfo (user:pass@)
 *   - host is present
 *   - host is not an IP literal
 *
 * Paired with allow_redirects => false + default TLS verification at every
 * fetch site, this covers the practical SSRF surface on hosted. No DNS
 * resolution is performed — TLS cert validation handles DNS-trickery, and
 * the https+no-redirect combination already blocks cloud metadata, internal
 * HTTP services, open-redirector chains, and non-HTTP schemes.
 *
 * Self-hosted deployments skip all checks — administrators own their
 * server, so reaching local services is not a threat boundary.
 */
class SafeExternalUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') {
            return;
        }

        $result = self::check($value);

        if (!$result['ok']) {
            $fail("The :attribute is not a safe URL: {$result['reason']}");
        }
    }

    /**
     * Validate a URL's SSRF-relevant shape. Self-hosted returns ok without
     * running any check.
     *
     * @return array{ok:bool, reason?:string, url?:string}
     */
    public static function check(string $url): array
    {
        if (Ninja::isSelfHost()) {
            return ['ok' => true, 'url' => $url];
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return ['ok' => false, 'reason' => 'Malformed URL'];
        }

        if (strtolower($parsed['scheme'] ?? '') !== 'https') {
            return ['ok' => false, 'reason' => 'Scheme must be https'];
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return ['ok' => false, 'reason' => 'Userinfo is not permitted'];
        }

        $host = strtolower($parsed['host'] ?? '');
        if ($host === '') {
            return ['ok' => false, 'reason' => 'Missing host'];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false || str_starts_with($host, '[')) {
            return ['ok' => false, 'reason' => 'IP literals are not permitted'];
        }

        return ['ok' => true, 'url' => $url];
    }
}
