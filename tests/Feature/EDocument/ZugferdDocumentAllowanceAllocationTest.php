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

    /**
     * @param array<int, array{tax_id: string, tax_rate: float, base_amount: float, total: float}> $taxMap
     */
    private function makeExporter(
        array $taxMap,
        float $documentDiscount,
        float $taxTotal,
        float $documentTotal,
        bool $usesInclusiveTaxes
    ): ZugferdEDocument {
        $lineItems = array_map(function (array $tax) use ($usesInclusiveTaxes): InvoiceItem {
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
        $invoice->setRawAttributes([
            'uses_inclusive_taxes' => $usesInclusiveTaxes,
            'line_items' => json_encode($lineItems, JSON_THROW_ON_ERROR),
            'total_taxes' => $taxTotal,
            'amount' => $documentTotal,
            'balance' => $documentTotal,
        ]);

        $taxCollection = collect($taxMap);
        $calculator = $usesInclusiveTaxes
            ? new class ($taxCollection, $documentDiscount, $taxTotal) extends InvoiceSumInclusive {
                public function __construct(
                    private readonly Collection $taxCollection,
                    private readonly float $documentDiscount,
                    private readonly float $taxTotal
                ) {
                }

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
            ) {
            }

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

        return $exporter;
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
