<?php

namespace Tests\Feature\EInvoice;

use App\Jobs\EDocument\ImportEDocument;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Vendor;
use App\Services\EDocument\Imports\SelfBilledInvoice;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Imports and validates the published type 389 (self-billed invoice) examples.
 *
 * The SB-* files are the unmodified Peppol BIS Self-Billing 3.0 examples
 * (https://docs.peppol.eu/poacc/self-billing/3.0/files/BIS-SelfBilling-Examples.zip).
 * The AU-/NZ- files are the A-NZ BIS 3.0 self-billing extension examples
 * (github.com/A-NZ-PEPPOL/A-NZ-PEPPOL-BIS-3.0 @ 9e0b901, since removed upstream).
 *
 * rules/*.sch are the unmodified Schematron sources published with the Peppol
 * Self-Billing 3.0.2 release (EN16931 1.3.16 plus the self-billing Peppol rules).
 * rules/*.xslt are those sources compiled with the ISO Schematron SVRL stylesheet already in the repo
 * (tests/Feature/EInvoice/Validation/iso_svrl_for_xslt2.xsl), the same way as the production Peppol stylesheets.
 *
 * The AU-/NZ- files use the A-NZ customization and GST, so only their UBL schema validity is asserted;
 * the EN16931 and Peppol self-billing rules do not apply to them.
 */
class SelfBilledInvoiceFixturesTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private const FIXTURE_DIR = 'tests/Fixtures/EDocument/self-billing/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        Storage::fake('public');
        Bus::fake();
        Http::preventStrayRequests();
    }

    private function fixture(string $file): string
    {
        return file_get_contents(base_path(self::FIXTURE_DIR . $file));
    }

    private function importFixture(string $file): Invoice|Expense
    {
        return (new ImportEDocument($this->fixture($file), $file, 'application/xml', $this->company))->handle();
    }

    private function skipWithoutSaxon(): void
    {
        if (!class_exists(\Saxon\SaxonProcessor::class)) {
            $this->markTestSkipped('saxon not installed');
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function peppolFixtureProvider(): array
    {
        return [
            'base example' => ['SB-base-example.xml'],
            'sales order reference' => ['SB-sales-order-example.xml'],
            'negative invoice correction' => ['SB-base-negative-inv-correction.xml'],
            'vat category S' => ['SB-Vat-category-S.xml'],
            'vat category E' => ['SB-vat-category-E.xml'],
            'vat category Z' => ['SB-vat-category-Z.xml'],
            'vat category O' => ['SB-vat-category-O.xml'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function fixtureProvider(): array
    {
        return self::peppolFixtureProvider() + [
            'australia' => ['AU-Self-Billing.xml'],
            'new zealand' => ['NZ-Self-Billing.xml'],
        ];
    }

    /**
     * file, number, buyer name, buyer country, currency, issue date, due date, buyer routing id,
     * po number, amount, total taxes, document lines (quantity, unit cost, tax rate), imported line count
     *
     * @return array<string, array<int, mixed>>
     */
    public static function importProvider(): array
    {
        return [
            'base example' => [
                'SB-base-example.xml', 'Snippet1', 'Buyer Official Name', 'SE', 'EUR', '2017-11-13', '2017-12-01', 'FR23342',
                '', 1656.25, 331.25, [[7, 400, 25], [-3, 500, 25]], 3,
            ],
            'sales order reference' => [
                'SB-sales-order-example.xml', 'Snippet1', 'Buyer Official Name', 'SE', 'EUR', '2017-11-13', '2017-12-01', 'FR23342',
                'NA', 1656.25, 331.25, [[7, 400, 25], [-3, 500, 25]], 3,
            ],
            'negative invoice correction' => [
                'SB-base-negative-inv-correction.xml', 'Correction1', 'Buyer Official Name', 'SE', 'EUR', '2017-11-13', '2017-12-01', 'FR23342',
                '', -1656.25, -331.25, [[-7, 400, 25], [3, 500, 25]], 3,
            ],
            'vat category S' => [
                'SB-Vat-category-S.xml', 'Snippet1', 'Buyer Official Name', 'SE', 'EUR', '2017-11-13', '2017-12-01', 'FR23342',
                '', 8550, 1550, [[10, 400, 25], [10, 200, 15], [10, 90, 25]], 5,
            ],
            'vat category E' => [
                'SB-vat-category-E.xml', 'Vat-Z', 'The Buyercompany', 'DK', 'GBP', '2018-08-30', null, 'DK12345678',
                '', 1200, 0, [[10, 120, 0]], 1,
            ],
            'vat category Z' => [
                'SB-vat-category-Z.xml', 'Vat-Z', 'The Buyercompany', 'DK', 'GBP', '2018-08-30', null, 'DK12345678',
                '', 1200, 0, [[10, 120, 0]], 1,
            ],
            'vat category O' => [
                'SB-vat-category-O.xml', 'Vat-O', 'The Buyercompany', 'NO', 'SEK', '2018-08-30', null, '987654325',
                '', 3200, 0, [[1, 3200, 0]], 1,
            ],
            'australia' => [
                'AU-Self-Billing.xml', 'Snippet1', 'Buyer Official Name', 'AU', 'AUD', '2019-07-29', '2019-08-30', '91888222000',
                'PurchaseOrderReference', 4262.12, 387.47, [[10, 59.99, 10], [2, 700, 10], [25, 74.99, 10]], 3,
            ],
            'new zealand' => [
                'NZ-Self-Billing.xml', 'INV_00482', 'Walls Incorporated', 'NZ', 'NZD', '2019-07-29', '2019-08-30', '9429033591476',
                'SOMEBLERB', 4455.85, 581.20, [[10, 59.99, 15], [2, 700, 15], [25, 74.99, 15]], 3,
            ],
        ];
    }

    #[DataProvider('fixtureProvider')]
    public function testFixtureIsDetectedAsSelfBilledInvoice(string $file): void
    {
        $this->assertTrue(SelfBilledInvoice::isSelfBilledInvoice($this->fixture($file)));
    }

    #[DataProvider('importProvider')]
    public function testFixtureImportsAsDraftInvoice(
        string $file,
        string $number,
        string $buyer_name,
        string $buyer_country,
        string $currency,
        string $issue_date,
        ?string $due_date,
        string $routing_id,
        string $po_number,
        float $amount,
        float $total_taxes,
        array $document_lines,
        int $line_count,
    ): void {
        $expense_count = Expense::count();
        $vendor_count = Vendor::count();

        $invoice = $this->importFixture($file);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status_id);
        $this->assertSame($number, $invoice->number);
        $this->assertSame($issue_date, $invoice->date);
        $this->assertSame($po_number, (string) $invoice->po_number);

        if ($due_date !== null) {
            $this->assertSame($due_date, $invoice->due_date);
        }

        $this->assertSame($buyer_name, $invoice->client->name);
        $this->assertSame($buyer_country, $invoice->client->country->iso_3166_2);
        $this->assertSame($currency, $invoice->client->currency()->code);
        $this->assertSame($routing_id, $invoice->client->routing_id);

        $this->assertEqualsWithDelta($amount, (float) $invoice->amount, 0.001);
        $this->assertEqualsWithDelta($total_taxes, (float) $invoice->total_taxes, 0.001);
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertSame(0, $invoice->payments()->count());
        $this->assertCount($line_count, $invoice->line_items);

        foreach ($document_lines as $index => [$quantity, $cost, $tax_rate]) {
            $this->assertEqualsWithDelta($quantity, (float) $invoice->line_items[$index]->quantity, 0.001, "line {$index} quantity");
            $this->assertEqualsWithDelta($cost, (float) $invoice->line_items[$index]->cost, 0.001, "line {$index} cost");
            $this->assertEqualsWithDelta($tax_rate, (float) $invoice->line_items[$index]->tax_rate1, 0.001, "line {$index} tax rate");
        }

        $stored = $invoice->fresh();
        $this->assertTrue(Str::isUuid($stored->backup->guid));
        $this->assertTrue($stored->isSelfBilled());
        $this->assertTrue($stored->isTaxImmutable());
        $this->assertSame($number, data_get($stored->e_invoice, 'Invoice.ID.value'));
        $this->assertSame(389, data_get($stored->e_invoice, 'Invoice.InvoiceTypeCode.value'));
        $this->assertContains("self-billed-{$invoice->id}.xml", $stored->documents()->pluck('name')->all());

        $this->assertSame($expense_count, Expense::count());
        $this->assertSame($vendor_count, Vendor::count());
    }

    #[DataProvider('fixtureProvider')]
    public function testFixtureReimportReturnsTheSameInvoice(string $file): void
    {
        $invoice = $this->importFixture($file);
        $invoice_count = Invoice::count();

        $repeated = $this->importFixture($file);

        $this->assertSame($invoice->id, $repeated->id);
        $this->assertSame($invoice->backup->guid, $repeated->fresh()->backup->guid);
        $this->assertSame($invoice_count, Invoice::count());
        $this->assertSame(1, $invoice->documents()->where('name', "self-billed-{$invoice->id}.xml")->count());
    }

    #[DataProvider('fixtureProvider')]
    public function testFixtureIsValidUblInvoice(string $file): void
    {
        $this->assertSame([], (new XsltDocumentValidator($this->fixture($file)))->validate()->getErrors()['xsd'] ?? []);
    }

    #[DataProvider('peppolFixtureProvider')]
    public function testPeppolFixturePassesEn16931AndSelfBillingRules(string $file): void
    {
        $this->skipWithoutSaxon();

        $this->assertSame([], $this->validateAgainstSelfBillingRules($this->fixture($file)));
    }

    /**
     * The harness must be able to fail: each mutation breaks exactly one rule that the unmodified example satisfies.
     */
    #[DataProvider('brokenDocumentProvider')]
    public function testSelfBillingRulesRejectBrokenDocument(string $search, string $replace, string $expected_rule): void
    {
        $this->skipWithoutSaxon();

        $xml = str_replace($search, $replace, $this->fixture('SB-base-example.xml'), $count);
        $this->assertSame(1, $count, 'mutation must apply to the fixture');

        $errors = $this->validateAgainstSelfBillingRules($xml);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString($expected_rule, implode("\n", $errors));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function brokenDocumentProvider(): array
    {
        return [
            'billing specification identifier' => [
                'poacc:selfbilling:3.0</cbc:CustomizationID>',
                'poacc:billing:3.0</cbc:CustomizationID>',
                'PEPPOL-EN16931-R004',
            ],
            'billing type code' => [
                '<cbc:InvoiceTypeCode>389</cbc:InvoiceTypeCode>',
                '<cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>',
                'PEPPOL-EN16931-P0100',
            ],
            'payable amount differs from totals' => [
                '<cbc:PayableAmount currencyID="EUR">1656.25</cbc:PayableAmount>',
                '<cbc:PayableAmount currencyID="EUR">1656.26</cbc:PayableAmount>',
                'BR-CO-16',
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function validateAgainstSelfBillingRules(string $xml): array
    {
        $validator = (new XsltDocumentValidator($xml))->setStyleSheets([
            '../' . self::FIXTURE_DIR . 'rules/CEN-EN16931-UBL.xslt',
            '../' . self::FIXTURE_DIR . 'rules/PEPPOL-EN16931-UBL-SB.xslt',
        ])->validate();

        $messages = [];

        foreach ($validator->getErrors() as $category => $errors) {
            foreach ((array) $errors as $message) {
                $messages[] = "[{$category}] {$message}";
            }
        }

        return $messages;
    }
}
