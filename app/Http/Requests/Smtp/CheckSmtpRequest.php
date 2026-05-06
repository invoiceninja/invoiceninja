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

namespace App\Http\Requests\Smtp;

use App\Http\Requests\Request;
use App\Utils\Ninja;

class CheckSmtpRequest extends Request
{
    private const ALLOWED_PORTS = [25, 465, 587, 2525];

    private const BLOCKED_METADATA_IPS = [
        '169.254.169.254', // AWS / GCP / Azure
        'fd00:ec2::254',   // AWS IPv6
        '100.100.100.200', // Alibaba
    ];

    private const BLOCKED_REBINDING_DOMAINS = [
        'nip.io',
        'sslip.io',
        'xip.io',
        'traefik.me',
        '1u.ms',
        'localtest.me',
        'lvh.me',
        'localhost.run',
    ];

    private const RESERVED_IPV6_CIDRS = [
        '::1/128',         // loopback
        'fc00::/7',        // ULA
        'fe80::/10',       // link-local
        '64:ff9b::/96',    // IPv4/IPv6 translation
        '2001:db8::/32',   // documentation
        'ff00::/8',        // multicast
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'smtp_host' => 'required|string|min:3',
            'smtp_port' => 'required|integer',
            'smtp_username' => 'required|min:3',
            'smtp_password' => 'required|min:3',
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator)
    {
        $validator->after(function ($validator) {
            $host = $this->normalizeHost((string) $this->input('smtp_host'));

            if ($host === '') {
                $validator->errors()->add('smtp_host', 'Invalid hostname');
                return;
            }

            if (Ninja::isHosted() && $this->isRebindingDomain($host)) {
                $validator->errors()->add('smtp_host', 'Invalid hostname');
                return;
            }

            $ips = $this->resolveHost($host);

            if (empty($ips)) {
                $validator->errors()->add('smtp_host', 'Unable to resolve hostname.');
                return;
            }

            foreach ($ips as $ip) {
                if (in_array($ip, self::BLOCKED_METADATA_IPS, true)) {
                    $validator->errors()->add('smtp_host', 'Invalid hostname');
                    return;
                }

                if (Ninja::isHosted() && !$this->isPublicIp($ip)) {
                    $validator->errors()->add('smtp_host', 'Invalid hostname');
                    return;
                }
            }

            if (Ninja::isHosted() && !in_array((int) $this->input('smtp_port'), self::ALLOWED_PORTS, true)) {
                $validator->errors()->add('smtp_port', 'Invalid port');
                return;
            }
        });
    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();

        $input = $this->input();

        if (isset($input['smtp_username']) && $input['smtp_username'] == '********') {

            $input['smtp_username'] = $company->smtp_username;
        }

        if (isset($input['smtp_password']) && $input['smtp_password'] == '********') {
            $input['smtp_password'] = $company->smtp_password;
        }

        if (isset($input['smtp_host']) && strlen($input['smtp_host']) >= 3) {

        } else {
            $input['smtp_host'] = $company->smtp_host;
        }

        if (!isset($input['smtp_port']) || !is_numeric($input['smtp_port'])) {
            $input['smtp_port'] = $company->smtp_port ?? 0;
        }

        $this->replace($input);
    }

    private function normalizeHost(string $input): string
    {
        $input = trim($input);
        if ($input === '') {
            return '';
        }

        // Reject inputs with paths, credentials, or query/fragment components — only bare host[:port] allowed.
        if (preg_match('~[/\\\\@?#]~', $input)) {
            return '';
        }

        // Strip a trailing :port (e.g. "smtp.example.com:587") before parse_url, which would otherwise treat
        // the leading segment as a URI scheme.
        if (preg_match('/^(.*):\d+$/', $input, $m)) {
            $input = $m[1];
        }

        $host = '';
        $parsed = parse_url('smtp://' . $input);
        if (isset($parsed['host'])) {
            $host = $parsed['host'];
        }

        if ($host === '') {
            return '';
        }

        // Strip IPv6 brackets if present.
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }

        $host = strtolower(rtrim($host, '.'));

        // IDN — convert to ASCII (punycode) so DNS lookups and validation work for non-ASCII domains.
        if (function_exists('idn_to_ascii') && preg_match('/[^\x20-\x7e]/', $host)) {
            $ascii = @idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if ($ascii !== false) {
                $host = $ascii;
            }
        }

        return $host;
    }

    /**
     * @return string[]
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        foreach ((@dns_get_record($host, DNS_A) ?: []) as $r) {
            if (isset($r['ip'])) {
                $ips[] = $r['ip'];
            }
        }

        foreach ((@dns_get_record($host, DNS_AAAA) ?: []) as $r) {
            if (isset($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }

        // Fallback for environments where dns_get_record returns empty (e.g. some musl/Alpine setups)
        // but gethostbyname still works. gethostbyname returns the input on failure.
        if (empty($ips)) {
            $resolved = @gethostbyname($host);
            if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP)) {
                $ips[] = $resolved;
            }
        }

        return $ips;
    }

    private function isRebindingDomain(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        foreach (self::BLOCKED_REBINDING_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }

    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (bool) filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false;
        }

        // Unwrap IPv4-mapped IPv6 (::ffff:1.2.3.4) and re-validate as IPv4 — some resolvers return AAAA
        // records in this form for hosts that are otherwise pure IPv4. Without unwrapping, every public
        // IPv4 in this form would be incorrectly rejected.
        if (preg_match('/^::ffff:(.+)$/i', $ip, $m) && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->isPublicIp($m[1]);
        }

        foreach (self::RESERVED_IPV6_CIDRS as $cidr) {
            if ($this->ipv6InCidr($ip, $cidr)) {
                return false;
            }
        }

        return true;
    }

    private function ipv6InCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remBits > 0) {
            $mask = (~((1 << (8 - $remBits)) - 1)) & 0xff;
            if ((ord($ipBin[$bytes]) & $mask) !== (ord($subnetBin[$bytes]) & $mask)) {
                return false;
            }
        }

        return true;
    }
}
