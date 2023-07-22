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

use Tests\TestCase;
use Tests\MockAccountData;
use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Invoice\CreateEInvoice;
use Illuminate\Support\Facades\Storage;
use horstoeko\zugferd\ZugferdDocumentReader;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

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
        $this->company->e_invoice_type = "EN16931";
        $this->invoice->client->routing_id = 'DE123456789';
        $this->invoice->client->save();
        $xinvoice = (new CreateEInvoice($this->invoice, false))->handle();
        $this->assertNotNull($xinvoice);
        $this->assertTrue(Storage::exists($xinvoice));
    }

     /**
      * @throws Exception
      */
     public function testValidityofXMLFile()
     {
         $this->company->e_invoice_type = "EN16931";
         $this->invoice->client->routing_id = 'DE123456789';
         $this->invoice->client->save();

         $xinvoice = (new CreateEInvoice($this->invoice, false))->handle();
         nlog(Storage::path($xinvoice));
         $document = ZugferdDocumentReader::readAndGuessFromFile(Storage::path($xinvoice));
         $document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $documentcurrency, $taxcurrency, $taxname, $documentlangeuage, $rest);
         $this->assertEquals($this->invoice->number, $documentno);
     }

     /**
      * @throws Exception
      */
     public function checkEmbededPDFFile()
     {
         $pdf = (new CreateEntityPdf($this->invoice->invitations()->first()))->handle();
         (new CreateEInvoice($this->invoice, true, $pdf))->handle();
         $document = ZugferdDocumentReader::readAndGuessFromFile($pdf);
         $document->getDocumentInformation($documentno, $documenttypecode, $documentdate, $documentcurrency, $taxcurrency, $taxname, $documentlangeuage, $rest);
         $this->assertEquals($this->invoice->number, $documentno);
     }
}
