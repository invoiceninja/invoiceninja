<?php

namespace Tests\Unit;

use App\Services\Download\ProtectedZipDownloadStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\MockAccountData;
use Tests\TestCase;
use ZipArchive;

class ProtectedZipDownloadStoreTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.default'));
        Cache::flush();
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

        Storage::assertExists($result->storage_path);
        $this->assertSame($this->company->file_path().'downloads/reports-2026-08-10.zip', $result->storage_path);
        $this->assertSame($result->storage_path, Cache::get($result->hash));
        $this->assertStringContainsString($result->hash, $result->url);
        $this->assertTrue(URL::hasValidSignature(request()->create($result->url)));

        $zip = new ZipArchive();
        $zip->open(Storage::path($result->storage_path));

        $this->assertSame(2, $zip->numFiles);
        $this->assertSame('report.csv', $zip->getNameIndex(0));
        $this->assertSame('report.pdf', $zip->getNameIndex(1));
        $this->assertSame('name,value', $zip->getFromName('report.csv'));
        $zip->close();
    }
}
