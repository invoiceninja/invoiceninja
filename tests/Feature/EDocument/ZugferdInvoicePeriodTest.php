<?php

namespace Tests\Feature\EDocument;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;
use App\Models\Country;
use App\Models\Product;
use Tests\MockAccountData;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\Factory\RecurringInvoiceToInvoiceFactory;
use App\Services\EDocument\Standards\ZugferdEDocument;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;

class ZugferdInvoicePeriodTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const BILLING_PERIOD = '//ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod';

    private const SERVICE_DATE = '//ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->invoice->date = '2026-11-11';
    }

    #[DataProvider('invoicePeriods')]
    public function testInvoicePeriodIsExportedInsteadOfServiceDate(array $period, ?string $expected_start, ?string $expected_end): void
    {
        $this->invoice->e_invoice = ['Invoice' => ['InvoicePeriod' => [$period]]];

        $xpath = $this->exportXpath();

        $this->assertSame(1, $this->nodeCount($xpath, self::BILLING_PERIOD));
        $this->assertSame($expected_start ?? '', $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:StartDateTime/udt:DateTimeString)'));
        $this->assertSame($expected_end ?? '', $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:EndDateTime/udt:DateTimeString)'));
        $this->assertSame(0, $this->nodeCount($xpath, self::SERVICE_DATE));
    }

    public static function invoicePeriods(): iterable
    {
        yield 'explicit dates' => [
            ['StartDate' => '2026-10-01', 'EndDate' => '2026-10-31'], '20261001', '20261031',
        ];
        yield 'serialized datetime objects' => [
            [
                'StartDate' => new \DateTime('2026-10-01'),
                'EndDate' => new \DateTime('2026-10-31'),
            ], '20261001', '20261031',
        ];
        yield 'start only' => [['StartDate' => '2026-10-01'], '20261001', null];
        yield 'end only' => [['EndDate' => '2026-10-31'], null, '20261031'];
        yield 'same day' => [
            ['StartDate' => '2026-10-01', 'EndDate' => '2026-10-01'], '20261001', '20261001',
        ];
    }

    #[DataProvider('serviceDates')]
    public function testInvoicePeriodTakesPrecedenceOverExplicitServiceDate(string $service_date): void
    {
        $this->invoice->e_invoice = ['Invoice' => [
            'InvoicePeriod' => [['StartDate' => '2026-10-01', 'EndDate' => '2026-10-31']],
            'Delivery' => [['ActualDeliveryDate' => $service_date]],
        ]];

        $xpath = $this->exportXpath();

        $this->assertSame('20261001', $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:StartDateTime/udt:DateTimeString)'));
        $this->assertSame('20261031', $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:EndDateTime/udt:DateTimeString)'));
        $this->assertSame(0, $this->nodeCount($xpath, self::SERVICE_DATE));
    }

    public static function serviceDates(): iterable
    {
        yield 'explicit service date' => ['2026-11-05'];
        yield 'unused invalid service date' => ['invalid'];
    }

    #[DataProvider('emptyInvoicePeriods')]
    public function testExplicitServiceDateIsUsedWithoutInvoicePeriod(?array $e_invoice): void
    {
        $e_invoice['Invoice']['Delivery'] = [['ActualDeliveryDate' => '2026-10-31']];
        $this->invoice->e_invoice = $e_invoice;

        $xpath = $this->exportXpath();

        $this->assertSame(0, $this->nodeCount($xpath, self::BILLING_PERIOD));
        $this->assertSame('20261031', $xpath->evaluate('string(' . self::SERVICE_DATE . '/ram:OccurrenceDateTime/udt:DateTimeString)'));
    }

    #[DataProvider('emptyInvoicePeriods')]
    public function testInvoiceDateIsUsedWithoutServiceDateOrUsablePeriod(?array $e_invoice): void
    {
        $this->invoice->e_invoice = $e_invoice;

        $xpath = $this->exportXpath();

        $this->assertSame(0, $this->nodeCount($xpath, self::BILLING_PERIOD));
        $this->assertSame('20261111', $xpath->evaluate('string(' . self::SERVICE_DATE . '/ram:OccurrenceDateTime/udt:DateTimeString)'));
    }

    public static function emptyInvoicePeriods(): iterable
    {
        yield 'no e-invoice data' => [null];
        yield 'empty period list' => [['Invoice' => ['InvoicePeriod' => []]]];
        yield 'empty period' => [['Invoice' => ['InvoicePeriod' => [new \stdClass()]]]];
        yield 'empty dates' => [['Invoice' => ['InvoicePeriod' => [['StartDate' => '', 'EndDate' => null]]]]];
        yield 'null dates' => [['Invoice' => ['InvoicePeriod' => [['StartDate' => null, 'EndDate' => null]]]]];
        yield 'null delivery date' => [['Invoice' => ['Delivery' => [['ActualDeliveryDate' => null]]]]];
    }

    public function testRecurringRelativePeriodIsExported(): void
    {
        $this->recurring_invoice->e_invoice = ['Invoice' => ['InvoicePeriod' => [[
            'Description' => 'first day of this month|last day of this month',
        ]]]];

        $generated_invoice = RecurringInvoiceToInvoiceFactory::create($this->recurring_invoice, $this->client);
        $generated_invoice->date = '2026-11-11';
        $generated_invoice->balance = $generated_invoice->amount;
        $generated_invoice->setRelation('client', $this->client);
        $generated_invoice->setRelation('company', $this->company);
        $generated_invoice->setRelation('user', $this->user);
        $this->invoice = $generated_invoice;

        $xpath = $this->exportXpath();
        $period = $generated_invoice->e_invoice->Invoice->InvoicePeriod[0];

        $this->assertSame(str_replace('-', '', $period->StartDate), $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:StartDateTime/udt:DateTimeString)'));
        $this->assertSame(str_replace('-', '', $period->EndDate), $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:EndDateTime/udt:DateTimeString)'));
        $this->assertSame(0, $this->nodeCount($xpath, self::SERVICE_DATE));
    }

    public function testInvoicePeriodArtifactPassesXsdAndXsltValidation(): void
    {
        $this->requireSaxon();

        $de_country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $settings = $this->company->settings;
        $settings->name = 'Invoice Period Test Seller';
        $settings->address1 = 'Seller Street 1';
        $settings->city = 'Hamburg';
        $settings->postal_code = '20095';
        $settings->country_id = $de_country_id;
        $settings->currency_id = '3';
        $settings->vat_number = 'DE923356489';
        $settings->e_invoice_type = 'EN16931';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = 'DE';

        $this->company->settings = $settings;
        $this->company->tax_data = $tax_data;
        $this->company->calculate_taxes = true;
        $this->company->save();

        $this->client->country_id = $de_country_id;
        $this->client->vat_number = 'DE923356488';
        $this->client->classification = 'business';
        $this->client->has_valid_vat_number = true;
        $this->client->name = 'Invoice Period Test Buyer';
        $this->client->address1 = 'Buyer Street 1';
        $this->client->city = 'Berlin';
        $this->client->postal_code = '10115';
        $this->client->save();

        $item = new InvoiceItem();
        $item->product_key = 'Monthly service';
        $item->notes = 'Services for October 2026';
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_name1 = 'MwSt';
        $item->tax_rate1 = 19;
        $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;

        $this->invoice->number = 'ZUGFERD-PERIOD-2026-001';
        $this->invoice->due_date = '2026-12-11';
        $this->invoice->line_items = [$item];
        $this->invoice->e_invoice = ['Invoice' => [
            'InvoicePeriod' => [['StartDate' => '2026-10-01', 'EndDate' => '2026-10-31']],
            'Delivery' => [['ActualDeliveryDate' => '2026-11-05']],
        ]];
        $this->invoice = $this->invoice->calc()->getInvoice();
        $this->invoice->balance = $this->invoice->amount;
        $this->invoice->setRelation('client', $this->client);
        $this->invoice->setRelation('company', $this->company);

        $xml = (new ZugferdEDocument($this->invoice))->run()->getXml();
        $artifact_path = base_path('tests/artifacts/zugferd/invoice-period.xml');
        File::ensureDirectoryExists(dirname($artifact_path));
        $this->assertSame(strlen($xml), file_put_contents($artifact_path, $xml));
        $artifact_xml = file_get_contents($artifact_path);
        $this->assertNotFalse($artifact_xml);

        $xpath = $this->exportXpath($artifact_xml);
        $this->assertSame('20261001', $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:StartDateTime/udt:DateTimeString)'));
        $this->assertSame('20261031', $xpath->evaluate('string(' . self::BILLING_PERIOD . '/ram:EndDateTime/udt:DateTimeString)'));
        $this->assertSame(0, $this->nodeCount($xpath, self::SERVICE_DATE));

        $validator = new XsltDocumentValidator($artifact_xml);
        $validator->setXsd('Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->setStyleSheets(['Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt']);
        $validator->validate();

        $this->assertSame([], $validator->getErrors(), json_encode($validator->getErrors(), JSON_PRETTY_PRINT));
    }

    private function requireSaxon(): void
    {
        try {
            new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Saxon processor not installed – skipping XSLT validation');
        }
    }

    private function exportXpath(?string $xml = null): DOMXPath
    {
        $xml ??= (new ZugferdEDocument($this->invoice))->run()->getXml();
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $xpath->registerNamespace('udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');

        return $xpath;
    }

    private function nodeCount(DOMXPath $xpath, string $expression): int
    {
        $nodes = $xpath->query($expression);
        $this->assertNotFalse($nodes);

        return $nodes->length;
    }
}
