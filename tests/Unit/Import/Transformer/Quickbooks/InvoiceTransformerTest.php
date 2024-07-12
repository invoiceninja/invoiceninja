<?php

namespace Tests\Unit\Import\Transformer\Quickbooks;

use Tests\TestCase;
use App\Import\Transformer\Quickbooks\InvoiceTransformer;

class InvoiceTransformerTest extends TestCase
{
    private $invoiceData;
    private $tranformedData;
    private $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock the company object
        $company = (new \App\Factory\CompanyFactory)->create(1234);

        // Read the JSON string from a file and decode into an associative array
        $this->invoiceData = json_decode( file_get_contents( app_path('/../tests/Mock/Response/Quickbooks/invoice.json') ), true);
        $this->transformer = new InvoiceTransformer($company);
        $this->transformedData = $this->transformer->transform($this->invoiceData['Invoice']);
    }

    public function testIsInstanceOf()
    {
        $this->assertInstanceOf(InvoiceTransformer::class, $this->transformer);
    }

    public function testTransformReturnsArray()
    {
       $this->assertIsArray($this->transformedData);
    }

    public function testTransformContainsNumber()
    {
        $this->assertArrayHasKey('number', $this->transformedData);
        $this->assertEquals($this->invoiceData['Invoice']['DocNumber'], $this->transformedData['number']);
    }

    public function testTransformContainsDueDate()
    {
        $this->assertArrayHasKey('due_date', $this->transformedData);
        $this->assertEquals(strtotime($this->invoiceData['Invoice']['DueDate']), strtotime($this->transformedData['due_date']));
    }

    public function testTransformContainsAmount()
    {
        $this->assertArrayHasKey('amount', $this->transformedData);
        $this->assertIsFloat($this->transformedData['amount']);
        $this->assertEquals($this->invoiceData['Invoice']['TotalAmt'], $this->transformedData['amount']);
    }
}
