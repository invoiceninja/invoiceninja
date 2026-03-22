<?php

namespace Tests\Unit\Import\Transformer\Quickbooks;

use Tests\TestCase;
use Tests\MockAccountData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Import\Transformer\Quickbooks\InvoiceTransformer;

class InvoiceTransformerTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private $invoiceData;
    private $tranformedData;
    private $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->markTestSkipped("NO BUENO");

        $this->makeTestData();
        $this->withoutExceptionHandling();
        Auth::setUser($this->user);
        // Read the JSON string from a file and decode into an associative array
        $this->invoiceData = json_decode(file_get_contents(app_path('/../tests/Mock/Quickbooks/Data/invoice.json')), true);
        $this->transformer = new InvoiceTransformer($this->company);
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

    public function testTransformContainsLineItems()
    {
        $this->assertArrayHasKey('line_items', $this->transformedData);
        $this->assertNotNull($this->transformedData['line_items']);
        $this->assertEquals(count($this->invoiceData['Invoice']["Line"]) - 1, count($this->transformedData['line_items']));
    }

    public function testTransformHasClient()
    {
        $this->assertArrayHasKey('client', $this->transformedData);
        $this->assertArrayHasKey('contacts', $this->transformedData['client']);
        $this->assertEquals($this->invoiceData['Invoice']['BillEmail']['Address'], $this->transformedData['client']['contacts'][0]['email']);
    }
}
