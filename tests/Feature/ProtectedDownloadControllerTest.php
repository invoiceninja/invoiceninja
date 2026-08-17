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

namespace Tests\Feature;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProtectedDownloadControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        config(['filesystems.default' => 'legacy-downloads']);

        Storage::fake('protected-downloads');
        Storage::fake('legacy-downloads');
        Cache::flush();
        URL::forceRootUrl('https://example.test');
    }

    public function testSignedUrlDownloadsStructuredRecordFromItsDisk(): void
    {
        $hash = Str::uuid()->toString();
        $expires_at = now()->addHour();
        $storage_path = 'downloads/internal-name.zip';

        Storage::disk('protected-downloads')->put($storage_path, 'archive contents');
        Cache::put($hash, [
            'disk' => 'protected-downloads',
            'path' => $storage_path,
            'download_name' => 'customer-report.zip',
            'expires_at' => $expires_at->timestamp,
        ], $expires_at);

        $url = URL::temporarySignedRoute('protected_download', $expires_at, ['hash' => $hash], absolute: false);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertDownload('customer-report.zip');
        $this->assertSame('archive contents', $response->streamedContent());
    }

    public function testSignedUrlSupportsLegacyStringRecord(): void
    {
        $hash = Str::uuid()->toString();
        $expires_at = now()->addHour();
        $storage_path = 'downloads/legacy-report.zip';

        Storage::disk('legacy-downloads')->put($storage_path, 'legacy contents');
        Cache::put($hash, $storage_path, $expires_at);

        $url = URL::temporarySignedRoute('protected_download', $expires_at, ['hash' => $hash], absolute: false);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertDownload('legacy-report.zip');
        $this->assertSame('legacy contents', $response->streamedContent());
    }

    public function testUnsignedUrlIsRejected(): void
    {
        $response = $this->get(route('protected_download', ['hash' => Str::uuid()->toString()]));

        $response->assertForbidden();
    }

    public function testExpiredSignedUrlIsRejected(): void
    {
        $url = URL::temporarySignedRoute(
            'protected_download',
            now()->subMinute(),
            ['hash' => Str::uuid()->toString()],
            absolute: false,
        );

        $response = $this->get($url);

        $response->assertForbidden();
    }

    public function testRelativeSignedUrlSurvivesHostAndSchemeChanges(): void
    {
        $hash = Str::uuid()->toString();
        $expires_at = now()->addHour();
        $storage_path = 'downloads/proxied-report.zip';

        Storage::disk('protected-downloads')->put($storage_path, 'archive contents');
        Cache::put($hash, [
            'disk' => 'protected-downloads',
            'path' => $storage_path,
            'download_name' => 'proxied-report.zip',
            'expires_at' => $expires_at->timestamp,
        ], $expires_at);

        $signed_path = URL::temporarySignedRoute(
            'protected_download',
            $expires_at,
            ['hash' => $hash],
            absolute: false,
        );

        $response = $this->get('http://proxy.example.test' . $signed_path);

        $response->assertOk();
        $response->assertDownload('proxied-report.zip');
    }
}
