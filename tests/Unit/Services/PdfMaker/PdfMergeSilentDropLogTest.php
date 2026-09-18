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

namespace Tests\Unit\Services\PdfMaker;

use App\Services\PdfMaker\PdfMerge;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class PdfMergeSilentDropLogTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        Facade::setFacadeApplication($this->container);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Mockery::close();

        parent::tearDown();
    }

    public function testLogsWhenPdfCannotBeDowngradedDueToMissingPaidModule(): void
    {
        $logger = Mockery::mock();
        $logger->shouldReceive('error')
            ->once()
            ->with('PDF attachment merge skipped, unable to downgrade PDF for embedding', Mockery::type('array'));

        $this->container->instance('log', $logger);

        $merge = new PdfMerge([$this->unparsablePdfContents()]);

        $result = $merge->run();

        $this->assertIsString($result);
    }

    private function unparsablePdfContents(): string
    {
        // Not a valid PDF stream, forces FPDI to throw a PdfParserException.
        return "%PDF-1.7\nnot a real pdf body";
    }
}
