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

namespace Tests\Feature\Pdf;

use App\Services\Pdf\ImageFetcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * These tests use Http::fake() to simulate redirect / content-type / size
 * scenarios. They depend on real DNS for example.com so that
 * SafeExternalUrl::check() passes its resolution step. example.com is
 * IANA-reserved and resolves to public Cloudflare IPs.
 */
class ImageFetcherTest extends TestCase
{
    private const PNG_MAGIC = "\x89PNG\r\n\x1a\n";

    private const JPEG_MAGIC = "\xFF\xD8\xFF";

    private ImageFetcher $fetcher;

    protected function setUp(): void
    {
        parent::setUp();
        // SafeExternalUrl short-circuits on self-hosted; force hosted for
        // the SSRF shape checks to exercise.
        config(['ninja.environment' => 'hosted']);
        Cache::flush();
        $this->fetcher = new ImageFetcher();
    }

    private function pngBody(): string
    {
        return self::PNG_MAGIC . str_repeat("\x00", 32);
    }

    private function jpegBody(): string
    {
        return self::JPEG_MAGIC . str_repeat("\x00", 32);
    }

    public function test_empty_string_returns_null(): void
    {
        $this->assertNull($this->fetcher->inline(''));
    }

    public function test_rejects_invalid_url_without_fetching(): void
    {
        Http::fake();

        $this->assertNull($this->fetcher->inline('http://example.com/logo.png'));

        Http::assertNothingSent();
    }

    public function test_rejects_private_ip_literal_without_fetching(): void
    {
        Http::fake();

        $this->assertNull($this->fetcher->inline('https://127.0.0.1/logo.png'));

        Http::assertNothingSent();
    }

    public function test_valid_data_uri_passes_through(): void
    {
        $payload = base64_encode($this->pngBody());
        $dataUri = "data:image/png;base64,{$payload}";

        $this->assertSame($dataUri, $this->fetcher->inline($dataUri));
    }

    public function test_invalid_data_uri_returns_null(): void
    {
        $payload = base64_encode('<script>alert(1)</script>');
        $this->assertNull($this->fetcher->inline("data:text/html;base64,{$payload}"));
    }

    public function test_302_redirect_is_hard_rejected(): void
    {
        Http::fake([
            'https://example.com/logo.png' => Http::response('', 302, ['Location' => 'http://127.0.0.1/']),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_301_redirect_is_hard_rejected(): void
    {
        Http::fake([
            'https://example.com/logo.png' => Http::response('', 301, ['Location' => 'https://evil.example/']),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_404_response_is_rejected(): void
    {
        Http::fake([
            'https://example.com/logo.png' => Http::response('not found', 404),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_500_response_is_rejected(): void
    {
        Http::fake([
            'https://example.com/logo.png' => Http::response('boom', 500),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_non_image_body_is_rejected_even_if_content_type_lies(): void
    {
        Http::fake([
            'https://example.com/logo.png' => Http::response('<html>not a png</html>', 200, ['Content-Type' => 'image/png']),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_image_body_is_rejected_when_content_type_is_html(): void
    {
        Http::fake([
            'https://example.com/logo.png' => Http::response($this->pngBody(), 200, ['Content-Type' => 'text/html']),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_oversize_body_is_rejected(): void
    {
        $body = self::PNG_MAGIC . str_repeat('A', ImageFetcher::MAX_BYTES + 1);

        Http::fake([
            'https://example.com/logo.png' => Http::response($body, 200, ['Content-Type' => 'image/png']),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_happy_path_png(): void
    {
        $body = $this->pngBody();

        Http::fake([
            'https://example.com/logo.png' => Http::response($body, 200, ['Content-Type' => 'image/png']),
        ]);

        $result = $this->fetcher->inline('https://example.com/logo.png');

        $this->assertIsString($result);
        $this->assertStringStartsWith('data:image/png;base64,', $result);
        $this->assertSame(base64_encode($body), substr($result, strlen('data:image/png;base64,')));
    }

    public function test_happy_path_jpeg(): void
    {
        $body = $this->jpegBody();

        Http::fake([
            'https://example.com/logo.jpg' => Http::response($body, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $result = $this->fetcher->inline('https://example.com/logo.jpg');

        $this->assertIsString($result);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $result);
    }

    public function test_second_call_hits_cache(): void
    {
        $body = $this->pngBody();

        Http::fake([
            'https://example.com/logo.png' => Http::response($body, 200, ['Content-Type' => 'image/png']),
        ]);

        $first = $this->fetcher->inline('https://example.com/logo.png');
        $second = $this->fetcher->inline('https://example.com/logo.png');

        $this->assertNotNull($first);
        $this->assertSame($first, $second);

        Http::assertSentCount(1);
    }

    public function test_connection_exception_returns_null(): void
    {
        Http::fake([
            'https://example.com/logo.png' => fn() => throw new ConnectionException('Connection timed out'),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));
    }

    public function test_failed_fetch_is_not_cached(): void
    {
        Http::fake([
            'https://example.com/logo.png' => Http::sequence()
                ->push('', 500)
                ->push($this->pngBody(), 200, ['Content-Type' => 'image/png']),
        ]);

        $this->assertNull($this->fetcher->inline('https://example.com/logo.png'));

        $second = $this->fetcher->inline('https://example.com/logo.png');
        $this->assertNotNull($second);
        $this->assertStringStartsWith('data:image/png;base64,', $second);
    }
}
