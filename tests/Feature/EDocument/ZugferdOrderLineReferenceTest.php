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
use App\Services\EDocument\Standards\ZugferdEDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * BT-132 (reference to the buyer's purchase order line) in the CII XML.
 *
 * The value is read from the proxy value on the line item,
 * e_invoice->InvoiceLine->OrderLineReference->LineID (CreditNoteLine for
 * credits, as in the UBL output), and is only written
 * together with the purchase order number of the document (BT-13).
 */
class ZugferdOrderLineReferenceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const LINE_REFERENCE = '//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:BuyerOrderReferencedDocument/ram:LineID';

    private const LINE_ISSUER_ID = '//ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID';

    private const HEADER_REFERENCE ='//ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
        $this->prepareInvoice();
    }

    public function testLineReferenceIsWrittenWithPoNumber(): void
    {
        $xpath = $this->xpath($this->buildXml('PO-12345', '00010'));

        $this->assertSame(['00010'], $this->values($xpath, self::LINE_REFERENCE));
        $this->assertSame([], $this->values($xpath, self::LINE_ISSUER_ID), 'The order number must not be repeated on the line (CII-SR-108).');
        $this->assertSame(['PO-12345'], $this->values($xpath, self::HEADER_REFERENCE));
    }

    public function testLineReferenceKeepsLeadingZeros(): void
    {
        $xpath = $this->xpath($this->buildXml('PO-12345', ' 00010 '));

        $this->assertSame(['00010'], $this->values($xpath, self::LINE_REFERENCE));
    }

    public function testLineReferenceIsSkippedWithoutPoNumber(): void
    {
        $xpath = $this->xpath($this->buildXml('', '00010'));

        $this->assertSame([], $this->values($xpath, self::LINE_REFERENCE));
        $this->assertSame([], $this->values($xpath, self::HEADER_REFERENCE));
    }

    public function testLineReferenceIsSkippedWithoutValue(): void
    {
        $xpath = $this->xpath($this->buildXml('PO-12345', null));

        $this->assertSame([], $this->values($xpath, self::LINE_REFERENCE));
        $this->assertSame(['PO-12345'], $this->values($xpath, self::HEADER_REFERENCE));
    }

    public function testLineReferenceIsSkippedWithBlankValue(): void
    {
        $xpath = $this->xpath($this->buildXml('PO-12345', '   '));

        $this->assertSame([], $this->values($xpath, self::LINE_REFERENCE));
    }

    public function testCreditReadsTheCreditNoteLineKey(): void
    {
        $xpath = $this->xpath($this->buildXml('PO-12345', '00020', 'CreditNoteLine', true));

        $this->assertSame(['00020'], $this->values($xpath, self::LINE_REFERENCE));
        $this->assertSame([], $this->values($xpath, self::LINE_ISSUER_ID));
        $this->assertSame(['PO-12345'], $this->values($xpath, self::HEADER_REFERENCE));
    }

    public function testCreditIgnoresTheInvoiceLineKey(): void
    {
        // Same rule as the UBL output, so one credit gives the same BT-132 in both formats.
        $xpath = $this->xpath($this->buildXml('PO-12345', '00010', 'InvoiceLine', true));

        $this->assertSame([], $this->values($xpath, self::LINE_REFERENCE));
        $this->assertSame(['PO-12345'], $this->values($xpath, self::HEADER_REFERENCE));
    }

    private function prepareInvoice(): void
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
        // Quietly, so the ClientObserver does not dispatch CheckVat: the live
        // VIES lookup is not part of BT-132 and would make this test depend on
        // an external service.
        $this->client->saveQuietly();
    }

    private function buildXml(?string $po_number, ?string $order_line, string $root = 'InvoiceLine', bool $credit = false): string
    {
        // A plain object, so the proxy value can be attached the way it
        // arrives from the API without a dynamic property on InvoiceItem.
        $item = (object) get_object_vars(new InvoiceItem());
        $item->product_key = 'Product 1';
        $item->notes = 'Description for product 1';
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_name1 = 'MwSt';
        $item->tax_rate1 = 19;
        $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
        $item->type_id = '1';

        if ($order_line !== null) {
            $item->e_invoice = json_decode(json_encode([
                $root => ['OrderLineReference' => ['LineID' => $order_line]],
            ]));
        }

        $document = $credit ? $this->credit : $this->invoice;
        $document->po_number = $po_number;
        $document->line_items = [$item];
        $document->uses_inclusive_taxes = false;
        $document->tax_rate1 = 0;
        $document->tax_name1 = '';
        $document->tax_rate2 = 0;
        $document->tax_name2 = '';
        $document->tax_rate3 = 0;
        $document->tax_name3 = '';
        $document = $credit ? $document->calc()->getCredit() : $document->calc()->getInvoice();
        $document->setRelation('client', $this->client);
        $document->setRelation('company', $this->company);

        return (new ZugferdEDocument($document))->run()->getXml();
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);

        return $xpath;
    }

    /**
     * @return array<int, string>
     */
    private function values(DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);
        $this->assertNotFalse($nodes);

        $values = [];
        foreach ($nodes as $node) {
            $values[] = trim($node->textContent);
        }

        return $values;
    }
}
