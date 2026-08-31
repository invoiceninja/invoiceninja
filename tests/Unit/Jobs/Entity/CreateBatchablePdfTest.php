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

namespace Tests\Unit\Jobs\Entity;

use Mockery;
use stdClass;
use Tests\TestCase;
use App\Exceptions\BatchPdfException;
use App\Jobs\Entity\CreateBatchablePdf;
use App\Jobs\Entity\CreateRawPdf;
use App\Services\PdfMaker\BatchPdfService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

class CreateBatchablePdfTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleCachesGeneratedPdfThroughBatchPdfService(): void
    {
        $invitation = $this->invitation('db-ninja-02');
        $raw_pdf = Mockery::mock('overload:' . CreateRawPdf::class);
        $raw_pdf->shouldReceive('handle')->once()->andReturn('raw-pdf');
        $batch_pdf_service = Mockery::mock(BatchPdfService::class);
        $batch_pdf_service->shouldReceive('cachePdf')
            ->once()
            ->with('batch-cache-key', 'raw-pdf');

        (new CreateBatchablePdf($invitation, 'batch-cache-key'))->handle($batch_pdf_service);

        $this->assertSame('db-ninja-02', config('database.default'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHandleLetsBatchPdfExceptionFailTheJob(): void
    {
        $invitation = $this->invitation('db-ninja-02');
        $raw_pdf = Mockery::mock('overload:' . CreateRawPdf::class);
        $raw_pdf->shouldReceive('handle')->once()->andReturn('raw-pdf');
        $batch_pdf_service = Mockery::mock(BatchPdfService::class);
        $batch_pdf_service->shouldReceive('cachePdf')
            ->once()
            ->with('batch-cache-key', 'raw-pdf')
            ->andThrow(new BatchPdfException('Unable to cache the batch PDF.'));

        $this->expectException(BatchPdfException::class);
        $this->expectExceptionMessage('Unable to cache the batch PDF.');

        (new CreateBatchablePdf($invitation, 'batch-cache-key'))->handle($batch_pdf_service);
    }

    private function invitation(string $db): stdClass
    {
        $company = new stdClass();
        $company->db = $db;
        $invitation = new stdClass();
        $invitation->company = $company;

        return $invitation;
    }
}
