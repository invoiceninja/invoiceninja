<?php

/**
 * Invoice Ninja (https://www.invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://www.invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\EDocument;

use DOMDocument;
use DOMXPath;
use ReflectionClass;
use ReflectionMethod;
use App\Models\Invoice;
use App\DataMapper\InvoiceItem;
use PHPUnit\Framework\TestCase;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use App\Services\EDocument\Standards\ZugferdEDocument;

class ZugferdLineDiscountBuilderTest extends TestCase
{
    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const UDT_NAMESPACE = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    public function testExporterEmitsFixedLineDiscountAsLineAllowance(): void
    {
        $document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);
        /** @var Invoice $invoice */
        $invoice = (new ReflectionClass(Invoice::class))->newInstanceWithoutConstructor();
        $invoice->setRawAttributes([
            'uses_inclusive_taxes' => false,
            'line_items' => json_encode([$this->discountedItem()], JSON_THROW_ON_ERROR),
        ]);

        $exporter = new ZugferdEDocument($invoice);
        $exporter->xdocument = $document;

        $setLineItems = new ReflectionMethod(ZugferdEDocument::class, 'setLineItems');
        $setLineItems->setAccessible(true);
        $setLineItems->invoke($exporter);

        $xpath = $this->xpath($document);
        $allowancePath = '//ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge';

        $this->assertSame(
            1,
            $this->nodeCount($xpath, $allowancePath),
            'The exporter did not emit the fixed discount as a BG-27 invoice line allowance.'
        );
        $this->assertSame('false', $this->singleValue($xpath, $allowancePath . '/ram:ChargeIndicator/udt:Indicator'));
        $this->assertSame('200.00', $this->singleValue($xpath, $allowancePath . '/ram:ActualAmount'));
        $this->assertSame('Discount', $this->singleValue($xpath, $allowancePath . '/ram:Reason'));
        $this->assertSame(
            0,
            $this->nodeCount($xpath, '//ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge'),
            'The exporter emitted the line discount as a BT-147 item price discount.'
        );
        $this->assertSame('500.00', $this->singleValue($xpath, '//ram:NetPriceProductTradePrice/ram:ChargeAmount'));
        $this->assertSame('800.00', $this->singleValue($xpath, '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:LineTotalAmount | //ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
    }

    public function testBuilderCannotSerializeBt147WithoutGrossPrice(): void
    {
        $document = $this->newPosition();
        $document->setDocumentPositionNetPrice(400.0);
        $document->addDocumentPositionGrossPriceAllowanceCharge(100.0, false);
        $document->setDocumentPositionLineSummation(800.0);

        $xpath = $this->xpath($document);

        $this->assertSame(
            0,
            $this->nodeCount($xpath, '//ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge/ram:ActualAmount')
        );

        $document = $this->newPosition();
        $document->setDocumentPositionGrossPrice(500.0);
        $document->addDocumentPositionGrossPriceAllowanceCharge(100.0, false);
        $document->setDocumentPositionNetPrice(400.0);
        $document->setDocumentPositionLineSummation(800.0);

        $xpath = $this->xpath($document);

        $this->assertSame(
            '100.00',
            $this->singleValue($xpath, '//ram:GrossPriceProductTradePrice/ram:AppliedTradeAllowanceCharge/ram:ActualAmount')
        );
    }

    public function testBuilderSerializesLineAllowanceAmountAndReason(): void
    {
        $document = $this->newPosition();
        $document->setDocumentPositionNetPrice(500.0);
        $document->addDocumentPositionAllowanceCharge(
            actualAmount: 200.0,
            isCharge: false,
            calculationPercent: null,
            basisAmount: null,
            reasonCode: null,
            reason: 'Discount'
        );
        $document->setDocumentPositionLineSummation(800.0);

        $xpath = $this->xpath($document);
        $allowancePath = '//ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge';

        $this->assertSame('false', $this->singleValue($xpath, $allowancePath . '/ram:ChargeIndicator/udt:Indicator'));
        $this->assertSame('200.00', $this->singleValue($xpath, $allowancePath . '/ram:ActualAmount'));
        $this->assertSame('Discount', $this->singleValue($xpath, $allowancePath . '/ram:Reason'));
        $this->assertSame('800.00', $this->singleValue($xpath, '//ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount'));
    }

    private function discountedItem(): InvoiceItem
    {
        $item = new InvoiceItem();
        $item->product_key = 'Discounted consulting';
        $item->notes = 'Two units with a fixed line discount';
        $item->quantity = 2;
        $item->cost = 500;
        $item->discount = 200;
        $item->is_amount_discount = true;
        $item->line_total = 800;
        $item->tax_amount = 0;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->tax_id = '';
        $item->type_id = 1;

        return $item;
    }

    private function newPosition(): ZugferdDocumentBuilder
    {
        $document = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);
        $document->addNewPosition('1');
        $document->setDocumentPositionQuantity(2, 'H87');

        return $document;
    }

    private function xpath(ZugferdDocumentBuilder $document): DOMXPath
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($document->getContent()));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);
        $xpath->registerNamespace('udt', self::UDT_NAMESPACE);

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
