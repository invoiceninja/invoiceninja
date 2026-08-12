<?php

namespace Tests\Unit;

use App\Export\CSV\ProductExport;
use App\Jobs\Report\PreviewReport;
use App\Services\Download\ProtectedZipDownloadStore;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

class PreviewReportProtectedDownloadTest extends TestCase
{
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

         if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }
        
        $this->makeTestData();
    }

    public function testShouldOfferProtectedDownloadWhenElapsedExceedsBrowserTimeout(): void
    {
        $job = new PreviewReport($this->company, [], ProductExport::class, 'hash', 'products.csv', $this->user);

        $method = new ReflectionMethod($job, 'shouldOfferProtectedDownload');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($job, microtime(true) - 5));
        $this->assertFalse($method->invoke($job, microtime(true) - 30));
        $this->assertTrue($method->invoke($job, microtime(true) - 60));
    }

    public function testSkipsProtectedDownloadStoreWhenReportCompletesWithinBrowserTimeout(): void
    {
        $this->mock(ProtectedZipDownloadStore::class)
            ->shouldNotReceive('store');

        $data = [
            'send_email' => false,
            'date_range' => 'all',
            'report_keys' => [],
            'include_deleted' => false,
            'user_id' => $this->user->id,
        ];

        (new PreviewReport($this->company, $data, ProductExport::class, 'preview-fast', 'products.csv', $this->user))->handle();
    }
}
