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

namespace Tests\Unit\Rules;

use App\Rules\SafeExternalUrl;
use Tests\TestCase;

/**
 * SafeExternalUrl runs only in hosted mode. setUp() forces hosted so the
 * shape checks are exercised; one test at the end verifies the self-hosted
 * short-circuit.
 */
class SafeExternalUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ninja.environment' => 'hosted']);
    }

    private function assertRejected(string $url, ?string $reasonContains = null): void
    {
        $result = SafeExternalUrl::check($url);
        $this->assertFalse($result['ok'] ?? false, "Expected URL to be rejected: {$url}");

        if ($reasonContains !== null) {
            $this->assertStringContainsStringIgnoringCase($reasonContains, $result['reason'] ?? '');
        }
    }

    private function assertAccepted(string $url): void
    {
        $result = SafeExternalUrl::check($url);
        $this->assertTrue($result['ok'] ?? false, "Expected URL to be accepted: {$url}");
        $this->assertSame($url, $result['url'] ?? null);
    }

    public function test_rejects_http_scheme(): void
    {
        $this->assertRejected('http://example.com/logo.png', 'https');
    }

    public function test_rejects_ftp_scheme(): void
    {
        $this->assertRejected('ftp://example.com/logo.png', 'https');
    }

    public function test_rejects_file_scheme(): void
    {
        $this->assertRejected('file:///etc/passwd', 'https');
    }

    public function test_rejects_javascript_scheme(): void
    {
        $this->assertRejected('javascript:alert(1)', 'https');
    }

    public function test_rejects_gopher_scheme(): void
    {
        $this->assertRejected('gopher://example.com/', 'https');
    }

    public function test_rejects_data_uri(): void
    {
        $this->assertRejected('data:image/png;base64,iVBOR', 'https');
    }

    public function test_rejects_userinfo(): void
    {
        $this->assertRejected('https://user:pass@example.com/logo.png', 'Userinfo');
    }

    public function test_rejects_userinfo_smuggling(): void
    {
        // parse_url extracts host=evil.com, user=example.com — rejected either way
        $this->assertRejected('https://example.com@evil.com/logo.png', 'Userinfo');
    }

    public function test_rejects_ipv4_literal_host(): void
    {
        $this->assertRejected('https://10.0.0.2/logo.png', 'IP literals');
    }

    public function test_rejects_public_ipv4_literal_host(): void
    {
        $this->assertRejected('https://1.1.1.1/logo.png', 'IP literals');
    }

    public function test_rejects_ipv6_literal_host(): void
    {
        $this->assertRejected('https://[::1]/logo.png', 'IP literals');
    }

    public function test_rejects_ipv4_mapped_ipv6_literal(): void
    {
        $this->assertRejected('https://[::ffff:127.0.0.1]/logo.png', 'IP literals');
    }

    public function test_rejects_malformed_url(): void
    {
        // Invalid port triggers parse_url false return.
        $this->assertRejected('https://example.com:xyz/', 'Malformed');
    }

    public function test_rejects_scheme_only_url(): void
    {
        // parse_url returns false on "https://" with no host.
        $this->assertRejected('https://', 'Malformed');
    }

    public function test_accepts_plain_https_url(): void
    {
        $this->assertAccepted('https://example.com/logo.png');
    }

    public function test_accepts_https_with_path_query_fragment_port(): void
    {
        // All of these are allowed now — they're not SSRF-load-bearing.
        // allow_redirects=false + TLS handle the residuals at fetch time.
        $this->assertAccepted('https://example.com:8443/path/to/logo.png?size=large#section');
    }

    public function test_accepts_https_with_non_image_extension(): void
    {
        // Path extension is not the SSRF primitive's concern.
        $this->assertAccepted('https://example.com/file.html');
    }

    public function test_self_hosted_short_circuits_all_checks(): void
    {
        config(['ninja.environment' => 'selfhost']);

        // Everything that would fail in hosted now passes.
        foreach ([
            'http://10.0.0.2/logo.png',
            'http://localhost/probe',
            'file:///etc/passwd',
            'https://user:pass@evil.com/x',
            'javascript:alert(1)',
        ] as $url) {
            $result = SafeExternalUrl::check($url);
            $this->assertTrue($result['ok'], "Expected self-hosted to short-circuit: {$url}");
        }
    }

    public function test_validate_callback_fires_on_rejection(): void
    {
        $rule = new SafeExternalUrl();
        $failed = false;
        $message = '';

        $rule->validate('source', 'http://127.0.0.1/logo.png', function ($m) use (&$failed, &$message) {
            $failed = true;
            $message = is_string($m) ? $m : (string) $m;
        });

        $this->assertTrue($failed);
        $this->assertStringContainsString('not a safe URL', $message);
    }

    public function test_validate_callback_silent_on_empty(): void
    {
        $rule = new SafeExternalUrl();
        $failed = false;
        $rule->validate('source', '', function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }

    public function test_validate_callback_silent_on_non_string(): void
    {
        $rule = new SafeExternalUrl();
        $failed = false;
        $rule->validate('source', null, function () use (&$failed) {
            $failed = true;
        });
        $this->assertFalse($failed);
    }
}
