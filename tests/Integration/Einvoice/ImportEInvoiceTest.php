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

namespace Tests\Integration\Einvoice;

use Tests\TestCase;
use InvoiceNinja\EInvoice\EInvoice;


/**
 * 
 */
class ImportEInvoiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testParsingDocument()
    {
        $peppol_doc = file_get_contents(base_path('tests/Integration/Einvoice/samples/peppol.xml'));

        //file present
        $this->assertNotNull($peppol_doc);
        
        $e = new EInvoice();
        $invoice = $e->decode('Peppol', $peppol_doc, 'xml');

        //decodes as expected
        $this->assertNotNull($invoice);

        //has prop we expect
        $this->assertObjectHasProperty('UBLVersionID', $invoice);
        
        //has hydrated correctly
        $this->assertInstanceOf(\InvoiceNinja\EInvoice\Models\Peppol\Invoice::class, $invoice);


        
    }
}