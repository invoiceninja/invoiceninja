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

namespace Tests\Unit;

use App\Services\Pdf\Purify;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurifyHostSafetyTest extends TestCase
{
    #[DataProvider('safeHostProvider')]
    public function test_safe_hosts_pass_through(string $url): void
    {
        $result = Purify::clean('<img src="' . $url . '">', true);

        $this->assertStringContainsString('src=', $result, "Expected src to be preserved for {$url}");
        $this->assertStringContainsString($url, $result, "Expected URL to be preserved for {$url}");
    }

    #[DataProvider('unsafeHostProvider')]
    public function test_unsafe_hosts_have_src_stripped(string $url): void
    {
        $result = Purify::clean('<img src="' . $url . '">', true);

        $this->assertStringNotContainsString('src=', $result, "Expected src to be stripped for {$url}");
        $this->assertStringNotContainsString($url, $result, "Expected URL not to appear in output for {$url}");
    }

    #[DataProvider('unsafeHostProvider')]
    public function test_unsafe_hosts_in_anchor_have_href_stripped(string $url): void
    {
        $result = Purify::clean('<a href="' . $url . '">link</a>', true);

        $this->assertStringNotContainsString('href=', $result, "Expected href to be stripped for {$url}");
        $this->assertStringNotContainsString($url, $result, "Expected URL not to appear in output for {$url}");
    }

    public static function safeHostProvider(): array
    {
        return [
            'plain public host'        => ['http://example.com'],
            'cdn over https'           => ['https://cdn.example.com/logo.png'],
            'numeric in path only'     => ['http://example.com/127.0.0.1/x.png'],
            'one numeric label'        => ['http://1.example.com'],
            'two numeric labels'       => ['http://1.2.example.com'],
            'three numeric labels'     => ['http://1.2.3.example.com'],
            '.local mid-string'        => ['http://my.local.example.com'],
            'rebinder name mid-string' => ['http://nip.io.example.com'],
            'aws s3 path-style'        => ['https://bucket.s3.amazonaws.com/x.png'],
            'long subdomain chain'     => ['https://a.b.c.d.e.example.com/x.png'],
        ];
    }

    public static function unsafeHostProvider(): array
    {
        return [
            'ip prefix loopback'    => ['http://127.0.0.1.attacker.com'],
            'ip prefix rfc1918 10'  => ['http://10.0.0.5.malicious.org'],
            'ip prefix rfc1918 192' => ['http://192.168.1.1.evil.test'],
            'ip prefix imds'        => ['http://169.254.169.254.foo.test'],
            'ip prefix public'      => ['http://1.2.3.4.example.com'],
            'mdns suffix'           => ['http://server.local'],
            'localhost suffix'      => ['http://anything.localhost'],
            'localdomain suffix'    => ['http://printer.localdomain'],
            'internal suffix'       => ['http://files.internal'],
            'intranet suffix'       => ['http://wiki.intranet'],
            'lan suffix'            => ['http://nas.lan'],
            'private suffix'        => ['http://server.private'],
            'corp suffix'           => ['http://hr.corp'],
            'home suffix'           => ['http://router.home'],
            'nip.io rebinder'       => ['http://10.0.0.1.nip.io'],
            'sslip.io rebinder'     => ['http://abc.sslip.io'],
            'xip.io rebinder'       => ['http://192.168.0.1.xip.io'],
            'traefik.me rebinder'   => ['http://anything.traefik.me'],
            'localtest.me rebinder' => ['http://something.localtest.me'],
            'lvh.me rebinder'       => ['http://anything.lvh.me'],
            'vcap.me rebinder'      => ['http://anything.vcap.me'],
            '1u.ms rebinder'        => ['http://anything.1u.ms'],
        ];
    }
}
