<?php

namespace Tests\Feature\EInvoice;

use App\Factory\CloneInvoiceFactory;
use App\Jobs\EDocument\EInvoicePullDocs;
use App\Jobs\EDocument\ImportEDocument;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Imports\SelfBilledInvoice;
use App\Services\EDocument\Imports\ParseEDocument;
use App\Services\EDocument\Imports\UblEDocument;
use App\Services\EDocument\Jobs\SendEDocument;
use App\Utils\TempFile;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Jobs\Storecove\ReceiveDocument;
use Tests\MockAccountData;
use Tests\TestCase;
use UnexpectedValueException;

class SelfBilledInvoiceImportTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        Storage::fake('public');
        Bus::fake();
        Http::preventStrayRequests();
    }

    private function xml(): string
    {
        return file_get_contents(base_path('tests/Fixtures/EDocument/self-billed-invoice.xml'));
    }

    private function importXml(?string $xml = null, bool $mark_sent = false): Invoice|Expense
    {
        $file = TempFile::UploadedFileFromRaw($xml ?? $this->xml(), 'self-billed.xml', 'application/xml');

        return (new UblEDocument($file, $this->company))->run(mark_sent: $mark_sent);
    }

    public function testXmlUploadCreatesDraftInvoiceWithBuyerLinesAndAttachments(): void
    {
        $expense_count = Expense::count();
        $vendor_count = Vendor::count();
        $this->company->calculate_taxes = true;
        $this->company->save();

        $invoice = (new ImportEDocument($this->xml(), 'invoice.xml', 'application/xml', $this->company))->handle();

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status_id);
        $this->assertSame('Self-billing Buyer Pty Ltd', $invoice->client->name);
        $this->assertSame('AU11111111111', $invoice->client->vat_number);
        $this->assertSame('buyer@example.test', $invoice->client->contacts->first()->email);
        $this->assertSame('SELF-389-001', $invoice->number);
        $this->assertSame('PO-123', $invoice->po_number);
        $this->assertSame('2026-11-01', $invoice->date);
        $this->assertSame('2026-12-01', $invoice->due_date);
        $this->assertSame('Buyer-issued invoice', $invoice->public_notes);
        $this->assertEquals(130, $invoice->amount);
        $this->assertCount(3, $invoice->line_items);
        $this->assertEquals(4, $invoice->line_items[0]->quantity);
        $this->assertEquals(25, $invoice->line_items[0]->cost);
        $this->assertEquals(10, $invoice->line_items[0]->discount);
        $this->assertEquals(10, $invoice->total_taxes);
        $this->assertSame('', $invoice->backup->guid);
        $this->assertFalse(property_exists($invoice->backup, 'self_billing'));
        $this->assertSame(389, data_get($invoice->fresh()->e_invoice, 'Invoice.InvoiceTypeCode.value'));
        $this->assertSame('SELF-389-001', data_get($invoice->fresh()->e_invoice, 'Invoice.ID.value'));
        $this->assertTrue($invoice->isTaxImmutable());
        $this->assertNull($invoice->next_send_date);
        $this->assertEquals($expense_count, Expense::count());
        $this->assertEquals($vendor_count, Vendor::count());
        $this->assertEqualsCanonicalizing(["self-billed-{$invoice->id}.xml", 'supporting.txt'], $invoice->documents()->pluck('name')->all());
    }

    public function testRepeatedAndWrappedXmlReusesInvoiceAndDocuments(): void
    {
        $invoice = $this->importXml();
        $wrapped_xml = '<StandardBusinessDocument>' . preg_replace('/<\?xml.*?\?>/', '', $this->xml()) . '</StandardBusinessDocument>';
        $invoice->delete();

        $this->assertTrue(SelfBilledInvoice::isSelfBilledInvoice($wrapped_xml));
        $repeated = $this->importXml($wrapped_xml);

        $this->assertSame($invoice->id, $repeated->id);
        $this->assertTrue($repeated->trashed());
        $this->assertSame(2, $repeated->documents()->count());
    }

    public function testExistingBuyerIsReusedWithoutOverwritingClient(): void
    {
        $this->client->vat_number = 'AU11111111111';
        $this->client->public_notes = 'Existing client note';
        $this->client->save();

        $invoice = $this->importXml();

        $this->assertSame($this->client->id, $invoice->client_id);
        $this->assertSame('Existing client note', $invoice->client->public_notes);
        $this->assertSame('Buyer-issued invoice', $invoice->public_notes);
    }

    public function testMismatchedTotalsRollBackWithoutCreatingExpenseOrClient(): void
    {
        $client_count = Client::count();
        $invoice_count = Invoice::count();
        $expense_count = Expense::count();

        try {
            (new ImportEDocument(str_replace('>130.00<', '>999.00<', $this->xml()), 'invoice.xml', 'application/xml', $this->company))->handle();
            $this->fail('Import should reject mismatched totals.');
        } catch (UnexpectedValueException $exception) {
            $this->assertStringContainsString('totals do not match', $exception->getMessage());
        }

        $this->assertSame($client_count, Client::count());
        $this->assertSame($invoice_count, Invoice::count());
        $this->assertSame($expense_count, Expense::count());
    }

    public function testReceiptActivatesInvoiceOnceAndCannotBeSentToPeppol(): void
    {
        $invoice = $this->importXml(mark_sent: true);
        $client_balance = $invoice->client->fresh()->balance;
        $repeated = $this->importXml(mark_sent: true);

        $this->assertSame(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertEquals(130, $invoice->balance);
        $this->assertEquals(130, $client_balance);
        $this->assertSame($invoice->id, $repeated->id);
        $this->assertEquals($client_balance, $invoice->client->fresh()->balance);
        $this->assertNull($invoice->service()->setReminder()->save()->next_send_date);

        $storecove = $this->createMock(Storecove::class);
        $storecove->expects($this->never())->method('getDocument');
        (new SendEDocument(Invoice::class, $invoice->id, $this->company->db))->handle($storecove);
        Bus::assertNotDispatched(SendEDocument::class);
    }

    public function testSelfHostedReceiptCreatesInvoiceAndFlushesAfterSuccess(): void
    {
        Http::fake(['*/api/einvoice/peppol/documents/flush' => Http::response([], 200)]);
        $document = [
            'guid' => 'received-389-guid',
            'document' => ['invoice' => ['self_billing_mode' => true]],
            'original_base64_xml' => base64_encode($this->xml()),
        ];

        $method = new \ReflectionMethod(EInvoicePullDocs::class, 'handleSuccess');
        $method->invoke(new EInvoicePullDocs(), [$document], $this->company, 'confirmation');

        $invoice = Invoice::where('company_id', $this->company->id)->where('number', 'SELF-389-001')->firstOrFail();
        $this->assertSame(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertTrue($invoice->documents()->where('name', 'received-389-guid.xml')->exists());
        Http::assertSentCount(1);
    }

    public function testHostedReceiptUsesTheSameInvoiceImporter(): void
    {
        if (!class_exists(ReceiveDocument::class)) {
            $this->markTestSkipped('Hosted module is unavailable.');
        }

        $this->company->legal_entity_id = 123456;
        $this->company->save();
        $storecove = $this->createMock(Storecove::class);
        $storecove->expects($this->exactly(2))->method('getDocument')->willReturnCallback(function (string $guid, string $format = 'json') {
            return $format === 'original'
                ? ['original' => base64_encode($this->xml())]
                : ['legal_entity_id' => $this->company->legal_entity_id, 'document' => ['invoice' => ['self_billing_mode' => true]]];
        });
        $job = new ReceiveDocument(['document_guid' => 'hosted-389-guid']);
        $reflection = new \ReflectionClass($job);
        $reflection->getProperty('storecove')->setValue($job, $storecove);
        $reflection->getMethod('parseJson')->invoke($job);
        $this->assertNull($reflection->getProperty('entity')->getValue($job));
        $reflection->getMethod('importDocument')->invoke($job);

        $invoice = $reflection->getProperty('entity')->getValue($job);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertTrue($invoice->documents()->where('name', 'hosted-389-guid.xml')->exists());
    }

    public function testOrdinaryInvoiceUploadStillCreatesExpense(): void
    {
        $xml = file_get_contents(base_path('tests/Integration/Einvoice/samples/peppol.xml'));
        $file = TempFile::UploadedFileFromRaw($xml, 'ordinary.xml', 'application/xml');
        $invoice_count = Invoice::count();

        $this->assertFalse(SelfBilledInvoice::isSelfBilledInvoice($xml));
        $entity = (new ParseEDocument($file, $this->company, import_invoices: true))->run();

        $this->assertInstanceOf(Expense::class, $entity);
        $this->assertSame($invoice_count, Invoice::count());
    }

    public function testDuplicateIdentityWithDifferentContentsIsRejected(): void
    {
        $this->importXml();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('different document contents');
        $this->importXml(str_replace('Buyer-issued invoice', 'Changed document', $this->xml()));
    }

    public function testPeppolReceiptActivatesPreviouslyUploadedDraft(): void
    {
        $draft = $this->importXml();
        $file = TempFile::UploadedFileFromRaw($this->xml(), 'received.xml', 'application/xml');
        $invoice = (new UblEDocument($file, $this->company))->run('received-after-upload', mark_sent: true);

        $this->assertSame($draft->id, $invoice->id);
        $this->assertSame(Invoice::STATUS_SENT, $invoice->status_id);
        $this->assertTrue($invoice->documents()->where('name', 'received-after-upload.xml')->exists());
        $this->assertEquals(130, $invoice->balance);
    }

    public function testReceiptAppliesPrepaymentThroughPaymentRepository(): void
    {
        $xml = str_replace(
            ['<cbc:PrepaidAmount currencyID="USD">0.00</cbc:PrepaidAmount>', '<cbc:PayableAmount currencyID="USD">130.00</cbc:PayableAmount>'],
            ['<cbc:PrepaidAmount currencyID="USD">30.00</cbc:PrepaidAmount>', '<cbc:PayableAmount currencyID="USD">100.00</cbc:PayableAmount>'],
            $this->xml(),
        );
        $invoice = $this->importXml($xml, mark_sent: true);

        $this->assertEquals(130, $invoice->amount);
        $this->assertEquals(100, $invoice->balance);
        $this->assertEquals(30, $invoice->paid_to_date);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame($invoice->id, $this->importXml($xml, mark_sent: true)->id);
        $this->assertSame(1, $invoice->payments()->count());
    }

    public function testNegativeSelfBilledInvoicePreservesSigns(): void
    {
        $xml = str_replace(
            ['>4</cbc:InvoicedQuantity>', '>1</cbc:InvoicedQuantity>', '>10.00</cbc:Amount>', '>130.00<'],
            ['>-4</cbc:InvoicedQuantity>', '>-1</cbc:InvoicedQuantity>', '>-10.00</cbc:Amount>', '>-130.00<'],
            $this->xml(),
        );
        $invoice = $this->importXml($xml, mark_sent: true);

        $this->assertEquals(-130, $invoice->amount);
        $this->assertEquals(-130, $invoice->balance);
        $this->assertEquals(-4, $invoice->line_items[0]->quantity);
    }

    public function testRoundingAmountIsPreservedWithoutAdditionalTax(): void
    {
        $xml = str_replace(
            '<cbc:PayableAmount currencyID="USD">130.00</cbc:PayableAmount>',
            '<cbc:PayableRoundingAmount currencyID="USD">0.02</cbc:PayableRoundingAmount><cbc:PayableAmount currencyID="USD">130.02</cbc:PayableAmount>',
            $this->xml(),
        );
        $invoice = $this->importXml($xml, mark_sent: true);

        $this->assertEquals(130.02, $invoice->amount);
        $this->assertEquals(10, $invoice->total_taxes);
        $this->assertEquals(130.02, $invoice->balance);
    }

    public function testSourceNumberCollisionPreservesExistingInvoice(): void
    {
        $this->invoice->number = 'SELF-389-001';
        $this->invoice->saveQuietly();
        $invoice = $this->importXml();

        $this->assertNotSame($this->invoice->id, $invoice->id);
        $this->assertNotSame('SELF-389-001', $invoice->number);
        $this->assertSame('SELF-389-001', data_get($invoice->e_invoice, 'Invoice.ID.value'));
        $this->assertSame('SELF-389-001', $this->invoice->fresh()->number);
    }

    public function testUploadedInvoiceUsesPeppolTaxRounding(): void
    {
        $line = '<cac:InvoiceLine><cbc:ID>1</cbc:ID><cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>'
            . '<cbc:LineExtensionAmount currencyID="USD">0.05</cbc:LineExtensionAmount>'
            . '<cac:Item><cbc:Name>Small item</cbc:Name><cac:ClassifiedTaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>10</cbc:Percent>'
            . '<cac:TaxScheme><cbc:ID>GST</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory></cac:Item>'
            . '<cac:Price><cbc:PriceAmount currencyID="USD">0.05</cbc:PriceAmount></cac:Price></cac:InvoiceLine>';
        $xml = preg_replace('/<cac:InvoiceLine>.*<\/cac:InvoiceLine>/s', str_repeat($line, 3), $this->xml());
        $xml = preg_replace('/<cac:AllowanceCharge>.*?<\/cac:AllowanceCharge>/s', '', $xml);
        $xml = preg_replace('/<cac:TaxTotal>.*?<\/cac:TaxTotal>/s', '<cac:TaxTotal><cbc:TaxAmount currencyID="USD">0.02</cbc:TaxAmount></cac:TaxTotal>', $xml);
        $xml = preg_replace('/<cac:LegalMonetaryTotal>.*?<\/cac:LegalMonetaryTotal>/s',
            '<cac:LegalMonetaryTotal><cbc:LineExtensionAmount currencyID="USD">0.15</cbc:LineExtensionAmount>'
            . '<cbc:TaxExclusiveAmount currencyID="USD">0.15</cbc:TaxExclusiveAmount>'
            . '<cbc:TaxInclusiveAmount currencyID="USD">0.17</cbc:TaxInclusiveAmount>'
            . '<cbc:PayableAmount currencyID="USD">0.17</cbc:PayableAmount></cac:LegalMonetaryTotal>', $xml);

        $invoice = $this->importXml($xml);

        $this->assertEquals(0.17, $invoice->amount);
        $this->assertEquals(0.02, $invoice->total_taxes);
    }

    public function testAmbiguousBuyerIdentifiersAreRejected(): void
    {
        $this->client->vat_number = 'AU11111111111';
        $this->client->saveQuietly();
        Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'routing_id' => '11111111111',
        ]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('matches multiple clients');
        $this->importXml();
    }

    public function testClonedInvoiceDoesNotKeepReceivedDocumentIdentity(): void
    {
        $invoice = $this->importXml();
        $clone = CloneInvoiceFactory::create($invoice, $this->user->id);

        $this->assertFalse($clone->isSelfBilled());
        $this->assertNull($clone->e_invoice);
        $this->assertFalse($clone->isTaxImmutable());
        $this->assertTrue($invoice->fresh()->isSelfBilled());
    }

    public function testDuplicateCheckAcceptsReorderedStoredDocumentKeys(): void
    {
        $invoice = $this->importXml();
        $invoice->e_invoice = ['Invoice' => array_reverse((array) $invoice->e_invoice->Invoice, true)];
        $invoice->saveQuietly();

        $this->assertSame($invoice->id, $this->importXml()->id);
    }

    public function testFailedSelfHostedImportDoesNotFlushDocuments(): void
    {
        Http::fake();
        $document = [
            'guid' => 'invalid-389-guid',
            'document' => ['invoice' => ['self_billing_mode' => true]],
            'original_base64_xml' => base64_encode(str_replace('>130.00<', '>999.00<', $this->xml())),
        ];

        try {
            $method = new \ReflectionMethod(EInvoicePullDocs::class, 'handleSuccess');
            $method->invoke(new EInvoicePullDocs(), [$document], $this->company, 'confirmation');
            $this->fail('Import should reject mismatched totals.');
        } catch (UnexpectedValueException $exception) {
            $this->assertStringContainsString('totals do not match', $exception->getMessage());
        }

        Http::assertNothingSent();
    }
}
