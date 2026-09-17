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

namespace Tests\Feature\EDocument;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;
use App\Models\Country;
use App\Models\Product;
use Tests\MockAccountData;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\EDocument\Standards\ZugferdEDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;

class ZugferdBuyerContactTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private string $zugferd_xsd = '/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd';

    private string $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    #[DataProvider('missingBuyerContactNameProvider')]
    public function testBuyerContactPersonNameIsOmittedWhenContactNameIsMissing(?string $firstName): void
    {
        $xml = $this->buildBuyerContactXml($firstName, 'Example');

        $this->assertNull($this->client->present()->primary_contact_name_or_null());

        $xpath = $this->xpath($xml);

        $this->assertSame(
            0,
            $this->nodeCount(
                $xpath,
                '//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:DefinedTradeContact/ram:PersonName'
            ),
            'Buyer contact PersonName should be omitted when the primary contact name is null or empty.'
        );
    }

    public function testBuyerContactPersonNameIsIncludedWhenContactNameIsPresent(): void
    {
        $xml = $this->buildBuyerContactXml('Jane', 'Example');

        $this->assertSame('Jane Example', $this->client->present()->primary_contact_name_or_null());
        $this->assertSame(
            'Jane Example',
            $this->singleValue(
                $this->xpath($xml),
                '//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:DefinedTradeContact/ram:PersonName'
            )
        );
    }

    public function testBuyerContactWithoutNamePassesEn16931SchematronValidation(): void
    {
        $this->requireSaxon();
        $this->prepareSchematronReadyInvoice();

        $xml = $this->buildBuyerContactXml('', 'Example');

        $this->assertNull($this->client->present()->primary_contact_name_or_null());
        $this->assertSame(
            0,
            $this->nodeCount(
                $this->xpath($xml),
                '//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:DefinedTradeContact/ram:PersonName'
            )
        );

        $this->assertXmlValid($xml, $this->zug_16931, 'buyer contact without PersonName');
    }

    public function testBuyerContactWithNamePassesEn16931SchematronValidation(): void
    {
        $this->requireSaxon();
        $this->prepareSchematronReadyInvoice();

        $xml = $this->buildBuyerContactXml('Jane', 'Example');

        $this->assertSame('Jane Example', $this->client->present()->primary_contact_name_or_null());
        $this->assertSame(
            'Jane Example',
            $this->singleValue(
                $this->xpath($xml),
                '//ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:DefinedTradeContact/ram:PersonName'
            )
        );

        $this->assertXmlValid($xml, $this->zug_16931, 'buyer contact with PersonName');
    }

    public static function missingBuyerContactNameProvider(): iterable
    {
        yield 'empty first name' => [''];
        yield 'null first name' => [null];
        yield 'single character first name' => ['A'];
    }

    private function prepareSchematronReadyInvoice(): void
    {
        $de_country_id = Country::where('iso_3166_2', 'DE')->first()->id;

        $settings = $this->company->settings;
        $settings->name = 'Test Company';
        $settings->address1 = 'Line 1 of address of the seller';
        $settings->city = 'Hamburg';
        $settings->postal_code = 'X123433';
        $settings->country_id = $de_country_id;
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
        $this->client->name = 'Test Client';
        $this->client->address1 = 'Client Street 1';
        $this->client->city = 'Berlin';
        $this->client->postal_code = '10115';
        $this->client->save();

        $item = new InvoiceItem();
        $item->product_key = 'Product 1';
        $item->notes = 'Description for product 1';
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_name1 = 'MwSt';
        $item->tax_rate1 = 19;
        $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
        $item->type_id = '1';

        $this->invoice->line_items = [$item];
        $this->invoice->uses_inclusive_taxes = false;
        $this->invoice->tax_rate1 = 0;
        $this->invoice->tax_name1 = '';
        $this->invoice->tax_rate2 = 0;
        $this->invoice->tax_name2 = '';
        $this->invoice->tax_rate3 = 0;
        $this->invoice->tax_name3 = '';
        $this->invoice = $this->invoice->calc()->getInvoice();
        $this->invoice->setRelation('client', $this->client);
        $this->invoice->setRelation('company', $this->company);
    }

    private function buildBuyerContactXml(?string $firstName, string $lastName): string
    {
        $this->contact->first_name = $firstName;
        $this->contact->last_name = $lastName;
        $this->contact->save();

        $this->client->refresh();
        $this->client->load('contacts', 'primary_contact', 'country');
        $this->invoice->setRelation('client', $this->client);

        return (new ZugferdEDocument($this->invoice))->run()->getXml();
    }

    private function requireSaxon(): void
    {
        try {
            new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Saxon processor not installed – skipping XSLT validation');
        }
    }

    private function assertXmlValid(string $xml, string $stylesheet, string $context = ''): void
    {
        $validator = new XsltDocumentValidator($xml);
        $validator->setStyleSheets([$stylesheet]);
        $validator->setXsd($this->zugferd_xsd);
        $validator->validate();

        $flat_errors = [];
        foreach ($validator->getErrors() as $errs) {
            foreach ((array) $errs as $e) {
                $flat_errors[] = $e;
            }
        }

        $this->assertCount(0, $flat_errors, "XML validation failed for: {$context}\n" . implode("\n", $flat_errors));
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);

        return $xpath;
    }

    private function nodeCount(DOMXPath $xpath, string $expression): int
    {
        $nodes = $xpath->query($expression);
        $this->assertNotFalse($nodes);

        return $nodes->length;
    }

    private function singleValue(DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length, "Expected exactly one node for XPath: {$expression}");

        return trim($nodes->item(0)?->textContent ?? '');
    }
}
