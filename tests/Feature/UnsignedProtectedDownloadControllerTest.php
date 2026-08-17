<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026 Invoice Ninja LLC (https://invoiceninja.com)
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

class UnsignedProtectedDownloadControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.protected_download_allow_unsigned' => true]);

        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('protected-downloads');
        Cache::flush();
    }

    public function testUnsignedUrlDownloadsStructuredRecordWhenEnabled(): void
    {
        $this->assertTrue(config('filesystems.protected_download_allow_unsigned'));

        $hash = Str::uuid()->toString();
        $expires_at = now()->addHour();
        $storage_path = 'downloads/unsigned-report.zip';

        Storage::disk('protected-downloads')->put($storage_path, 'archive contents');
        Cache::put($hash, [
            'disk' => 'protected-downloads',
            'path' => $storage_path,
            'download_name' => 'unsigned-report.zip',
            'expires_at' => $expires_at->timestamp,
        ], $expires_at);

        $url = URL::route('protected_download', ['hash' => $hash]);

        $this->assertNull(parse_url($url, PHP_URL_QUERY));

        $response = $this->get($url);

        $response->assertOk();
        $response->assertDownload('unsigned-report.zip');
    }

    public function testSignedUrlStillDownloadsWhenUnsignedAccessIsEnabled(): void
    {
        $hash = Str::uuid()->toString();
        $expires_at = now()->addHour();
        $storage_path = 'downloads/signed-report.zip';

        Storage::disk('protected-downloads')->put($storage_path, 'archive contents');
        Cache::put($hash, [
            'disk' => 'protected-downloads',
            'path' => $storage_path,
            'download_name' => 'signed-report.zip',
            'expires_at' => $expires_at->timestamp,
        ], $expires_at);

        $url = URL::temporarySignedRoute(
            'protected_download',
            $expires_at,
            ['hash' => $hash],
            absolute: false,
        );

        $response = $this->get($url);

        $response->assertOk();
        $response->assertDownload('signed-report.zip');
    }

    public function testUnsignedUrlStillRejectsExpiredRecord(): void
    {
        $hash = Str::uuid()->toString();
        $storage_path = 'downloads/expired-report.zip';

        Storage::disk('protected-downloads')->put($storage_path, 'archive contents');
        Cache::forever($hash, [
            'disk' => 'protected-downloads',
            'path' => $storage_path,
            'download_name' => 'expired-report.zip',
            'expires_at' => now()->subMinute()->timestamp,
        ]);

        $response = $this->get(URL::route('protected_download', ['hash' => $hash]));

        $response->assertSeeText('File no longer available');
        $this->assertStringNotContainsString('archive contents', $response->getContent());
    }
}
