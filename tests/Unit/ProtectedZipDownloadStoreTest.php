<?php

namespace Tests\Unit;

use App\Services\Download\ProtectedZipDownloadStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;
use ZipArchive;

class ProtectedZipDownloadStoreTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.default' => 'public',
            'filesystems.protected_download_disk' => 'protected-downloads',
        ]);

        Storage::fake('public');
        Storage::fake('protected-downloads');
        Cache::flush();
        Event::fake();
        Queue::fake();
        URL::forceRootUrl('https://example.test');

        $this->makeTestData();
    }

    public function testStoreCreatesZipUploadsFileAndReturnsSignedUrl(): void
    {
        $result = app(ProtectedZipDownloadStore::class)->store(
            [
                ['file' => base64_encode('name,value'), 'file_name' => 'report.csv', 'mime' => 'text/csv'],
                ['file' => base64_encode('%PDF-1.4'), 'file_name' => 'report.pdf', 'mime' => 'application/pdf'],
            ],
            'reports-2026-08-10.zip',
            $this->company,
            $this->user,
        );

        Storage::disk('protected-downloads')->assertExists($result->storage_path);
        Storage::disk('public')->assertMissing($result->storage_path);
        $this->assertSame($this->company->file_path() . 'downloads/reports-2026-08-10.zip', $result->storage_path);
        $this->assertSame([
            'disk' => 'protected-downloads',
            'path' => $result->storage_path,
            'download_name' => 'reports-2026-08-10.zip',
            'expires_at' => $result->expires_at->timestamp,
        ], Cache::get($result->hash));
        $this->assertStringContainsString($result->hash, $result->url);
        $this->assertTrue(URL::hasValidSignature(request()->create($result->url), absolute: false));

        $zip = new ZipArchive();
        $zip->open(Storage::disk('protected-downloads')->path($result->storage_path));

        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('report.csv', $zip->getNameIndex(0));
        $this->assertSame('report.pdf', $zip->getNameIndex(1));
        $this->assertSame('name,value', $zip->getFromName('report.csv'));
        $zip->close();
    }

    public function testStoreStandardizesInvalidLegacyBase64(): void
    {
        try {
            app(ProtectedZipDownloadStore::class)->store(
                [
                    ['file' => 'not-valid-base64!', 'file_name' => 'report.csv', 'mime' => 'text/csv'],
                ],
                'reports.zip',
                $this->company,
            );

            $this->fail('Expected legacy attachment decoding to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Unable to create protected download archive.', $exception->getMessage());
            $this->assertSame(500, $exception->getCode());
            $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        }
    }
}
