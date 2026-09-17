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

use App\Services\Pdf\Purify;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PurifyInlineImagesTest extends TestCase
{
    private const PNG_MAGIC = "\x89PNG\r\n\x1a\n";

    private const TRANSPARENT_PNG_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';

    protected function setUp(): void
    {
        parent::setUp();

        config(['ninja.environment' => 'hosted']);

        Cache::flush();
    }

    private function pngBody(): string
    {
        return self::PNG_MAGIC . str_repeat("\x00", 32);
    }

    public function test_without_inline_flag_remote_url_is_preserved(): void
    {
        Http::fake();

        $url = 'https://example.com/logo.png';
        $result = Purify::clean('<img src="' . $url . '">', true);

        $this->assertStringContainsString($url, $result);
        Http::assertNothingSent();
    }

    public function test_with_inline_flag_disabled_remote_url_is_preserved(): void
    {
        Http::fake();

        $url = 'https://example.com/logo.png';
        $result = Purify::clean('<img src="' . $url . '">', true, false);

        $this->assertStringContainsString($url, $result);
        Http::assertNothingSent();
    }

    public function test_with_inline_flag_enabled_inlines_successful_fetch(): void
    {
        $url = 'https://example.com/logo.png';

        Http::fake([
            $url => Http::response($this->pngBody(), 200, ['Content-Type' => 'image/png']),
        ]);

        $result = Purify::clean('<img src="' . $url . '">', true, true);

        $this->assertStringContainsString('data:image/png;base64,', $result);
        $this->assertStringNotContainsString($url, $result);
        Http::assertSentCount(1);
    }

    public function test_with_inline_flag_enabled_failed_fetch_uses_placeholder(): void
    {
        $url = 'https://example.com/logo.png';

        Http::fake([
            $url => Http::response('', 404),
        ]);

        $result = Purify::clean('<img src="' . $url . '">', true, true);

        $this->assertStringContainsString(self::TRANSPARENT_PNG_DATA_URI, $result);
        $this->assertStringNotContainsString($url, $result);
    }

    public function test_existing_data_uri_img_is_unchanged(): void
    {
        Http::fake();

        $payload = base64_encode($this->pngBody());
        $data_uri = 'data:image/png;base64,' . $payload;

        $result = Purify::clean('<img src="' . $data_uri . '">', true, true);

        $this->assertStringContainsString($data_uri, $result);
        Http::assertNothingSent();
    }
}
