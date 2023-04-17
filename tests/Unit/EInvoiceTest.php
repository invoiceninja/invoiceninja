<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Invoice\CreateXInvoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;
use horstoeko\zugferd\ZugferdDocumentReader;

/**
 * @test
 * @covers  App\Jobs\Invoice\CreateXInvoice
 */
class EInvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
        $this->makeTestData();
    }

    public function testEInvoiceGenerates()
    {
        $xinvoice = (new CreateXInvoice($this->invoice, false))->handle();
        $this->assertNotNull($xinvoice);
        $this->assertFileExists($xinvoice);
    }

    /**
     * @throws Exception
     */
    public function testValidityofXMLFile()
    {
        $xinvoice = (new CreateXInvoice($this->invoice, false))->handle();
        $document = ZugferdDocumentReader::readAndGuessFromFile($xinvoice);
        $document ->getDocumentInformation($documentno);
        $this->assertEquals($this->invoice->number, $documentno);
    }

    /**
     * @throws Exception
     */
    public function checkEmbededPDFFile()
    {
        $pdf = (new CreateEntityPdf($this->invoice->invitations()->first()));
        (new CreateXInvoice($this->invoice, true, $pdf))->handle();
        $document = ZugferdDocumentReader::readAndGuessFromFile($pdf);
        $document ->getDocumentInformation($documentno);
        $this->assertEquals($this->invoice->number, $documentno);
    }
}
