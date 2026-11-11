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
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;
use App\DataMapper\InvoiceItem;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use App\Services\EDocument\Standards\ZugferdEDocument;

class ZugferdDocumentAllowanceAllocationTest extends TestCase
{
    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const UDT_NAMESPACE = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    /**
     * @param array<int, array{tax_id: string, tax_rate: float, base_amount: float, total: float}> $taxMap
     * @param array<int, string> $expectedAllowanceAmounts
     */
    #[DataProvider('taxGroupRoundingScenarios')]
    public function testDocumentAllowanceAllocationsAlwaysReconcileToHeaderTotal(
        array $taxMap,
        float $documentDiscount,
        float $taxTotal,
        float $documentTotal,
        array $expectedAllowanceAmounts,
        bool $usesInclusiveTaxes
    ): void {
        $exporter = $this->makeExporter(
            $taxMap,
            $documentDiscount,
            $taxTotal,
            $documentTotal,
            $usesInclusiveTaxes
        );

        $this->invoke($exporter, 'setDocumentTaxes');
        $this->invoke($exporter, 'setDocumentSummation');

        $xpath = $this->xpath($exporter->getXml());
        $allowancePath = '//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge'
            . '[ram:ChargeIndicator/udt:Indicator="false"]/ram:ActualAmount';
        $allowanceNodes = $xpath->query($allowancePath);

        $this->assertNotFalse($allowanceNodes);

        $actualAllowanceAmounts = [];
        $actualAllowanceTotal = 0.0;

        foreach ($allowanceNodes as $allowanceNode) {
            $amount = trim($allowanceNode->textContent);
            $actualAllowanceAmounts[] = $amount;
            $actualAllowanceTotal += (float) $amount;
        }

        $headerAllowanceTotal = $this->singleValue(
            $xpath,
            '//ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:AllowanceTotalAmount'
        );

        $this->assertSame($expectedAllowanceAmounts, $actualAllowanceAmounts);
        $this->assertSame(number_format($documentDiscount, 2, '.', ''), $headerAllowanceTotal);
        $this->assertSame($headerAllowanceTotal, number_format($actualAllowanceTotal, 2, '.', ''));
    }

    public static function taxGroupRoundingScenarios(): iterable
    {
        yield 'one cent across two equal VAT groups' => [
            [
                ['tax_id' => '1', 'tax_rate' => 19.0, 'base_amount' => 100.0, 'total' => 19.0],
                ['tax_id' => '1', 'tax_rate' => 7.0, 'base_amount' => 100.0, 'total' => 7.0],
            ],
            0.01,
            26.0,
            225.99,
            ['0.01'],
            false,
        ];

        yield 'two cents across three equal VAT groups' => [
            [
                ['tax_id' => '1', 'tax_rate' => 19.0, 'base_amount' => 100.0, 'total' => 19.0],
                ['tax_id' => '1', 'tax_rate' => 7.0, 'base_amount' => 100.0, 'total' => 7.0],
                ['tax_id' => '1', 'tax_rate' => 5.0, 'base_amount' => 100.0, 'total' => 5.0],
            ],
            0.02,
            31.0,
            330.98,
            ['0.01', '0.01'],
            false,
        ];

        yield 'one net cent across two inclusive VAT groups' => [
            [
                ['tax_id' => '1', 'tax_rate' => 19.0, 'base_amount' => 100.0, 'total' => 19.0],
                ['tax_id' => '1', 'tax_rate' => 7.0, 'base_amount' => 100.0, 'total' => 7.0],
            ],
            0.01,
            26.0,
            225.99,
            ['0.01'],
            true,
        ];
    }

    public function testZeroTaxAllowanceResidualStaysWithItsTaxCategory(): void
    {
        $taxMap = [
            ['tax_id' => '5', 'tax_rate' => 0.0, 'base_amount' => 100.0, 'total' => 0.0],
            ['tax_id' => '8', 'tax_rate' => 0.0, 'base_amount' => 100.0, 'total' => 0.0],
        ];
        $exporter = $this->makeExporter($taxMap, 0.01, 0.0, 199.99, false);

        $this->invoke($exporter, 'setDocumentTaxes');
        $this->invoke($exporter, 'setDocumentSummation');

        $xpath = $this->xpath($exporter->getXml());
        $settlement = '//ram:ApplicableHeaderTradeSettlement';

        $this->assertSame(
            '0.01',
            $this->singleValue(
                $xpath,
                $settlement . '/ram:SpecifiedTradeAllowanceCharge'
                    . '[ram:CategoryTradeTax/ram:CategoryCode="E"]/ram:ActualAmount'
            )
        );
        $this->assertSame(
            '99.99',
            $this->singleValue(
                $xpath,
                $settlement . '/ram:ApplicableTradeTax[ram:CategoryCode="E"]/ram:BasisAmount'
            )
        );
        $this->assertSame(
            '100.00',
            $this->singleValue(
                $xpath,
                $settlement . '/ram:ApplicableTradeTax[ram:CategoryCode="Z"]/ram:BasisAmount'
            )
        );
        $this->assertSame(
            '0.01',
            $this->singleValue(
                $xpath,
                $settlement . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:AllowanceTotalAmount'
            )
        );
    }

    #[DataProvider('mixedVatScenarios')]
    public function testMixedVatBreakdownKeepsZeroRatedLinesInTheirOwnCategory(
        bool $usesInclusiveTaxes,
        string $zeroTaxId,
        string $zeroTaxName,
        string $expectedCategory,
        bool $expectsExemptionReason
    ): void {
        $taxMap = [
            ['tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL, 'tax_rate' => 7.0, 'base_amount' => 200.0, 'total' => 14.0],
            ['tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL, 'tax_rate' => 19.0, 'base_amount' => 100.0, 'total' => 19.0],
        ];

        if ($zeroTaxName !== '') {
            $taxMap[] = ['tax_id' => $zeroTaxId, 'tax_rate' => 0.0, 'base_amount' => 50.0, 'total' => 0.0];
        }

        $lineItems = [
            $this->lineItem(100, 7, Product::PRODUCT_TYPE_PHYSICAL, 'VAT', $usesInclusiveTaxes),
            $this->lineItem(100, 7, Product::PRODUCT_TYPE_PHYSICAL, 'VAT', $usesInclusiveTaxes),
            $this->lineItem(100, 19, Product::PRODUCT_TYPE_PHYSICAL, 'VAT', $usesInclusiveTaxes),
            $this->lineItem(50, 0, (int) $zeroTaxId, $zeroTaxName, $usesInclusiveTaxes),
        ];

        $exporter = $this->makeExporter(
            $taxMap,
            0.0,
            33.0,
            383.0,
            $usesInclusiveTaxes,
            $lineItems
        );

        $this->invoke($exporter, 'setDocumentTaxes');

        $xpath = $this->xpath($exporter->getXml());
        $taxes = '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax';

        $this->assertSame(3, $xpath->query($taxes)?->length);
        $this->assertTaxBreakdown($xpath, 'S', '7.00', '200.00', '14.00');
        $this->assertTaxBreakdown($xpath, 'S', '19.00', '100.00', '19.00');
        $this->assertTaxBreakdown($xpath, $expectedCategory, '0.00', '50.00', '0.00');
        $this->assertSame(
            0,
            $xpath->query(
                $taxes . '[ram:CategoryCode="S"]/ram:ExemptionReason'
                . ' | ' . $taxes . '[ram:CategoryCode="S"]/ram:ExemptionReasonCode'
            )?->length
        );

        $zeroTax = $taxes . '[ram:CategoryCode="' . $expectedCategory . '"]';
        $reasonCount = $xpath->query(
            $zeroTax . '/ram:ExemptionReason | ' . $zeroTax . '/ram:ExemptionReasonCode'
        )?->length;

        $expectsExemptionReason
            ? $this->assertGreaterThan(0, $reasonCount)
            : $this->assertSame(0, $reasonCount);
    }

    public static function mixedVatScenarios(): iterable
    {
        yield 'exclusive exempt line without tax name' => [
            false,
            (string) Product::PRODUCT_TYPE_EXEMPT,
            '',
            'E',
            true,
        ];

        yield 'inclusive exempt line without tax name' => [
            true,
            (string) Product::PRODUCT_TYPE_EXEMPT,
            '',
            'E',
            true,
        ];

        yield 'exclusive explicit zero-rated line' => [
            false,
            (string) Product::PRODUCT_TYPE_ZERO_RATED,
            'VAT',
            'Z',
            false,
        ];

        yield 'inclusive explicit zero-rated line' => [
            true,
            (string) Product::PRODUCT_TYPE_ZERO_RATED,
            'VAT',
            'Z',
            false,
        ];

        yield 'exclusive zero-rated line without tax name' => [
            false,
            (string) Product::PRODUCT_TYPE_ZERO_RATED,
            '',
            'Z',
            false,
        ];
    }

    public function testMixedVatBreakdownDoesNotMergeExemptAndZeroRatedGroupsAtZeroPercent(): void
    {
        $taxMap = [
            ['tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL, 'tax_rate' => 19.0, 'base_amount' => 100.0, 'total' => 19.0],
            ['tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT, 'tax_rate' => 0.0, 'base_amount' => 40.0, 'total' => 0.0],
            ['tax_id' => (string) Product::PRODUCT_TYPE_ZERO_RATED, 'tax_rate' => 0.0, 'base_amount' => 60.0, 'total' => 0.0],
        ];
        $lineItems = [
            $this->lineItem(100, 19, Product::PRODUCT_TYPE_PHYSICAL, 'VAT'),
            $this->lineItem(40, 0, Product::PRODUCT_TYPE_EXEMPT, 'VAT'),
            $this->lineItem(60, 0, Product::PRODUCT_TYPE_ZERO_RATED, 'VAT'),
        ];
        $exporter = $this->makeExporter($taxMap, 0.0, 19.0, 219.0, false, $lineItems);

        $this->invoke($exporter, 'setDocumentTaxes');

        $xpath = $this->xpath($exporter->getXml());
        $taxes = '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax';

        $this->assertSame(3, $xpath->query($taxes)?->length);
        $this->assertTaxBreakdown($xpath, 'E', '0.00', '40.00', '0.00');
        $this->assertTaxBreakdown($xpath, 'Z', '0.00', '60.00', '0.00');
    }

    public function testUntaxedDocumentSurchargeUsesItsOwnExemptVatBreakdown(): void
    {
        $taxMap = [
            ['tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL, 'tax_rate' => 7.0, 'base_amount' => 200.0, 'total' => 14.0],
            ['tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL, 'tax_rate' => 19.0, 'base_amount' => 100.0, 'total' => 19.0],
        ];
        $lineItems = [
            $this->lineItem(100, 7, Product::PRODUCT_TYPE_PHYSICAL, 'VAT'),
            $this->lineItem(100, 7, Product::PRODUCT_TYPE_PHYSICAL, 'VAT'),
            $this->lineItem(100, 19, Product::PRODUCT_TYPE_PHYSICAL, 'VAT'),
        ];
        $exporter = $this->makeExporter(
            $taxMap,
            0.0,
            33.0,
            383.0,
            false,
            $lineItems,
            [
                'custom_surcharge1' => 50.0,
                'custom_surcharge_tax1' => false,
            ]
        );

        $this->invoke($exporter, 'setDocumentTaxes');
        $this->invoke($exporter, 'setCustomSurcharges');

        $xpath = $this->xpath($exporter->getXml());
        $settlement = '//ram:ApplicableHeaderTradeSettlement';

        $this->assertTaxBreakdown($xpath, 'S', '7.00', '200.00', '14.00');
        $this->assertTaxBreakdown($xpath, 'S', '19.00', '100.00', '19.00');
        $this->assertTaxBreakdown($xpath, 'E', '0.00', '50.00', '0.00');
        $this->assertSame(
            '50.00',
            $this->singleValue(
                $xpath,
                $settlement . '/ram:SpecifiedTradeAllowanceCharge'
                    . '[ram:ChargeIndicator/udt:Indicator="true"]'
                    . '[ram:CategoryTradeTax/ram:CategoryCode="E"]'
                    . '[ram:CategoryTradeTax/ram:RateApplicablePercent="0.00"]'
                    . '/ram:ActualAmount'
            )
        );
    }

    /**
     * @param array<int, array{tax_id: string, tax_rate: float, base_amount: float, total: float}> $taxMap
     * @param array<int, InvoiceItem>|null $lineItems
     * @param array<string, mixed> $documentAttributes
     */
    private function makeExporter(
        array $taxMap,
        float $documentDiscount,
        float $taxTotal,
        float $documentTotal,
        bool $usesInclusiveTaxes,
        ?array $lineItems = null,
        array $documentAttributes = []
    ): ZugferdEDocument {
        $lineItems ??= array_map(function (array $tax) use ($usesInclusiveTaxes): InvoiceItem {
            $item = new InvoiceItem();
            $item->quantity = 1;
            $item->cost = $usesInclusiveTaxes
                ? round(100 * (1 + ($tax['tax_rate'] / 100)), 2)
                : 100;
            $item->discount = 0;
            $item->line_total = $item->cost;
            $item->tax_name1 = 'VAT';
            $item->tax_rate1 = $tax['tax_rate'];
            $item->tax_id = $tax['tax_id'];
            $item->type_id = 1;

            return $item;
        }, $taxMap);

        $invoice = new Invoice();
        $invoice->setRawAttributes(array_merge([
            'uses_inclusive_taxes' => $usesInclusiveTaxes,
            'line_items' => json_encode($lineItems, JSON_THROW_ON_ERROR),
            'total_taxes' => $taxTotal,
            'amount' => $documentTotal,
            'balance' => $documentTotal,
        ], $documentAttributes));

        $taxCollection = collect($taxMap);
        $calculator = $usesInclusiveTaxes
            ? new class ($taxCollection, $documentDiscount, $taxTotal) extends InvoiceSumInclusive {
                public function __construct(
                    private readonly Collection $taxCollection,
                    private readonly float $documentDiscount,
                    private readonly float $taxTotal
                ) {}

                public function getTaxMap(): Collection
                {
                    return $this->taxCollection;
                }

                public function getTotalDiscount(): float
                {
                    return $this->documentDiscount;
                }

                public function getTotalTaxes(): float
                {
                    return $this->taxTotal;
                }

                public function getTotalNetSurcharges(): float
                {
                    return 0.0;
                }
            }
        : new class ($taxCollection, $documentDiscount, $taxTotal) extends InvoiceSum {
            public function __construct(
                private readonly Collection $taxCollection,
                private readonly float $documentDiscount,
                private readonly float $taxTotal
            ) {}

            public function getTaxMap(): Collection
            {
                return $this->taxCollection;
            }

            public function getTotalDiscount(): float
            {
                return $this->documentDiscount;
            }

            public function getTotalTaxes(): float
            {
                return $this->taxTotal;
            }

            public function getTotalSurcharges(): float
            {
                return 0.0;
            }
        };

        $exporter = new ZugferdEDocument($invoice);
        $exporter->xdocument = ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_EN16931);

        $client = new Client();
        $client->setRawAttributes(['is_tax_exempt' => false]);

        $calculatorProperty = new ReflectionProperty($exporter, 'calc');
        $calculatorProperty->setValue($exporter, $calculator);

        $clientProperty = new ReflectionProperty($exporter, 'client');
        $clientProperty->setValue($exporter, $client);

        $exemptionReasonProperty = new ReflectionProperty($exporter, 'exemption_reason_code');
        $exemptionReasonProperty->setValue($exporter, 'VATEX-EU-O');

        return $exporter;
    }

    private function lineItem(
        float $netAmount,
        float $taxRate,
        int $taxId,
        string $taxName,
        bool $usesInclusiveTaxes = false
    ): InvoiceItem {
        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = $usesInclusiveTaxes
            ? round($netAmount * (1 + ($taxRate / 100)), 2)
            : $netAmount;
        $item->line_total = $item->cost;
        $item->discount = 0;
        $item->tax_name1 = $taxName;
        $item->tax_rate1 = $taxRate;
        $item->tax_id = (string) $taxId;
        $item->type_id = 1;

        return $item;
    }

    private function assertTaxBreakdown(
        DOMXPath $xpath,
        string $category,
        string $rate,
        string $basis,
        string $tax
    ): void {
        $path = '//ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax'
            . '[ram:CategoryCode="' . $category . '" and ram:RateApplicablePercent="' . $rate . '"]';

        $this->assertSame($basis, $this->singleValue($xpath, $path . '/ram:BasisAmount'));
        $this->assertSame($tax, $this->singleValue($xpath, $path . '/ram:CalculatedAmount'));
    }

    private function invoke(ZugferdEDocument $exporter, string $method): void
    {
        $reflectionMethod = new ReflectionMethod($exporter, $method);
        $reflectionMethod->invoke($exporter);
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);
        $xpath->registerNamespace('udt', self::UDT_NAMESPACE);

        return $xpath;
    }

    private function singleValue(DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        $this->assertNotFalse($nodes);
        $this->assertSame(1, $nodes->length, "Expected exactly one node for XPath: {$expression}");

        return trim($nodes->item(0)?->textContent ?? '');
    }
}
