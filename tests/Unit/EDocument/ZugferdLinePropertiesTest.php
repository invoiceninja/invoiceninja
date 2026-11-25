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

namespace Tests\Unit\EDocument;

use App\DataMapper\InvoiceItem;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Services\EDocument\Standards\ZugferdEDocument;
use DOMDocument;
use DOMXPath;
use Illuminate\Validation\ValidationException;
use InvoiceNinja\EInvoice\Models\Peppol\CreditNoteLineType\CreditNoteLine;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;

class ZugferdLinePropertiesTest extends TestCase
{
    private const LINE = '//ram:IncludedSupplyChainTradeLineItem';

    public static function documentScenarios(): iterable
    {
        yield 'invoice shorthand' => [false, false, 1000];
        yield 'invoice wrapped' => [false, true, 1000];
        yield 'credit shorthand' => [true, false, 1000];
        yield 'credit wrapped' => [true, true, 1000];
        yield 'negative invoice' => [false, true, -1000];
    }

    #[DataProvider('documentScenarios')]
    public function testMetadataReachesCiiWithoutChangingCalculationsOrSource(
        bool $is_credit,
        bool $wrapped,
        int $amount,
    ): void {
        $identifier = fn (string $value) => $wrapped ? ['value' => $value] : $value;
        $metadata = [
            'ID' => $identifier('999'),
            'LineExtensionAmount' => ['amount' => '99999', 'currencyID' => 'EUR'],
            'Item' => [
                'Name' => 'Metadata must not replace the product name',
                'SellersItemIdentification' => ['ID' => $identifier('00042')],
                'BuyersItemIdentification' => ['ID' => $identifier('0')],
            ],
            'OrderLineReference' => [['LineID' => $identifier('003')]],
        ];
        $entity = $this->entity([$this->item()], [$metadata], $is_credit);
        $entity->amount = $amount;
        $before = $entity->getAttributes();
        $exporter = new ZugferdEDocument($entity);

        $items = (new ReflectionMethod($exporter, 'prepareLineItems'))->invoke($exporter);
        $line_class = $is_credit ? CreditNoteLine::class : InvoiceLine::class;
        $this->assertInstanceOf($line_class, $items[0]->e_invoice);

        $xml = $this->exportLines($exporter);
        $xpath = $this->xpath($xml);
        $product_path = self::LINE . '/ram:SpecifiedTradeProduct';
        $settlement_path = self::LINE . '/ram:SpecifiedLineTradeSettlement';

        $this->assertSame('00042', $this->value($xpath, $product_path . '/ram:SellerAssignedID'));
        $this->assertSame('0', $this->value($xpath, $product_path . '/ram:BuyerAssignedID'));
        $this->assertSame('Consulting', $this->value($xpath, $product_path . '/ram:Name'));
        $this->assertSame('1', $this->value($xpath, self::LINE . '/ram:AssociatedDocumentLineDocument/ram:LineID'));
        $this->assertSame('003', $this->value($xpath, self::LINE . '/ram:SpecifiedLineTradeAgreement/ram:BuyerOrderReferencedDocument/ram:LineID'));
        $this->assertSame(0, $xpath->query(self::LINE . '/ram:SpecifiedLineTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID')->length);
        $this->assertSame('PO-001', $this->value($xpath, '//ram:ApplicableHeaderTradeAgreement/ram:BuyerOrderReferencedDocument/ram:IssuerAssignedID'));
        $this->assertSame('500.00', $this->value($xpath, self::LINE . '/ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount'));
        $this->assertSame('2.00', $this->value($xpath, self::LINE . '/ram:SpecifiedLineTradeDelivery/ram:BilledQuantity'));
        $this->assertSame('200.00', $this->value($xpath, $settlement_path . '/ram:SpecifiedTradeAllowanceCharge/ram:ActualAmount'));
        $this->assertSame('800.00', $this->value($xpath, $settlement_path . '/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
        $this->assertSame('21.00', $this->value($xpath, $settlement_path . '/ram:ApplicableTradeTax/ram:RateApplicablePercent'));
        $this->assertSame($before, $entity->getAttributes());
        $this->assertFalse(property_exists($entity->line_items[0], 'e_invoice'));
        $this->assertSame($xml, $this->exportLines($exporter));
    }

    public function testEmptyAndMissingMetadataPreserveLinePositions(): void
    {
        $entity = $this->entity(
            [$this->item(), $this->item(), $this->item(), $this->item()],
            [null, [], ['Item' => ['BuyersItemIdentification' => ['ID' => '00003']]]],
        );

        $xpath = $this->xpath($this->exportLines(new ZugferdEDocument($entity)));

        $this->assertSame(4, $xpath->query(self::LINE)->length);
        $this->assertSame(1, $xpath->query(self::LINE . '/ram:SpecifiedTradeProduct/ram:BuyerAssignedID')->length);
        $this->assertSame('00003', $this->value($xpath, '(' . self::LINE . ')[3]/ram:SpecifiedTradeProduct/ram:BuyerAssignedID'));
    }

    public function testMissingMetadataKeepsExistingLineOutput(): void
    {
        $entity = $this->entity([$this->item()], null);
        $exporter = new ZugferdEDocument($entity);
        $without_metadata = $this->exportLines($exporter);
        $entity->e_invoice = (object) ['Invoice' => (object) ['InvoiceLine' => [(object) []]]];

        $this->assertSame($without_metadata, $this->exportLines($exporter));
    }

    public static function unsupportedProfiles(): iterable
    {
        yield 'basic' => [ZugferdProfiles::PROFILE_BASIC];
        yield 'basic without lines' => [ZugferdProfiles::PROFILE_BASICWL];
        yield 'minimum' => [ZugferdProfiles::PROFILE_MINIMUM];
    }

    #[DataProvider('unsupportedProfiles')]
    public function testUnsupportedProfilesRejectMetadata(int $profile): void
    {
        $entity = $this->entity([$this->item()], [
            ['Item' => ['BuyersItemIdentification' => ['ID' => '0']]],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('The selected ZUGFeRD profile cannot represent these line properties.');

        $this->exportLines(new ZugferdEDocument($entity), $profile);
    }

    public function testOrderLineReferenceRequiresTheExistingHeaderPurchaseOrder(): void
    {
        $entity = $this->entity([$this->item()], [
            ['OrderLineReference' => [['LineID' => '003']]],
        ]);
        $entity->po_number = '';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Purchase order line references require a header purchase order reference.');

        $this->exportLines(new ZugferdEDocument($entity));
    }

    public function testMultipleOrderLineReferencesAreNotSilentlyDropped(): void
    {
        $entity = $this->entity([$this->item()], [
            ['OrderLineReference' => [['LineID' => '003'], ['LineID' => '004']]],
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ZUGFeRD supports one purchase order line reference per line.');

        $this->exportLines(new ZugferdEDocument($entity));
    }

    private function entity(array $items, ?array $metadata, bool $is_credit = false): Invoice|Credit
    {
        $entity = $is_credit ? new Credit() : new Invoice();
        $source_root = $is_credit ? 'CreditNote' : 'Invoice';
        $line_property = $is_credit ? 'CreditNoteLine' : 'InvoiceLine';
        $entity->setRawAttributes([
            'uses_inclusive_taxes' => false,
            'po_number' => 'PO-001',
            'line_items' => json_encode($items, JSON_THROW_ON_ERROR),
            'e_invoice' => $metadata === null
                ? null
                : json_encode([$source_root => [$line_property => $metadata]], JSON_THROW_ON_ERROR),
        ]);

        return $entity;
    }

    private function item(): InvoiceItem
    {
        $item = new InvoiceItem();
        $item->product_key = 'Consulting';
        $item->notes = 'Two units with a fixed line discount';
        $item->quantity = 2;
        $item->cost = 500;
        $item->discount = 200;
        $item->is_amount_discount = true;
        $item->line_total = 800;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 21;
        $item->tax_id = '1';

        return $item;
    }

    private function exportLines(
        ZugferdEDocument $exporter,
        int $profile = ZugferdProfiles::PROFILE_EN16931,
    ): string {
        $client = new Client();
        $client->setRawAttributes(['is_tax_exempt' => false]);
        (new ReflectionProperty($exporter, 'client'))->setValue($exporter, $client);
        $exporter->xdocument = ZugferdDocumentBuilder::createNew($profile);
        (new ReflectionMethod($exporter, 'setPoNumber'))->invoke($exporter);
        (new ReflectionMethod($exporter, 'setLineItems'))->invoke($exporter);

        return $exporter->xdocument->getContent();
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');

        return $xpath;
    }

    private function value(DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        $this->assertCount(1, $nodes, $expression);

        return $nodes->item(0)->textContent;
    }
}
