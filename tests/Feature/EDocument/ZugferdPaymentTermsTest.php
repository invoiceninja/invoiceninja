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
use Tests\MockAccountData;
use App\Services\EDocument\Standards\ZugferdEDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ZugferdPaymentTermsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const UDT_NAMESPACE = 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100';

    private const PAYMENT_TERMS = '//ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testPaymentTermsIncludeDueDate(): void
    {
        $xpath = $this->buildPaymentTermsXpath('2026-09-30', '2026-10-31');

        $this->assertSame('20261031', $this->singleValue($xpath, self::PAYMENT_TERMS . '/ram:DueDateDateTime/udt:DateTimeString'));
        $this->assertSame('102', $this->singleValue($xpath, self::PAYMENT_TERMS . '/ram:DueDateDateTime/udt:DateTimeString/@format'));
    }

    public function testPaymentTermsDescriptionUsesTotalDaysUntilDueDate(): void
    {
        $xpath = $this->buildPaymentTermsXpath('2026-09-30', '2026-10-31');

        $this->assertSame(
            ctrans('texts.xinvoice_payable', ['payeddue' => 31, 'paydate' => '2026-10-31']),
            $this->singleValue($xpath, self::PAYMENT_TERMS . '/ram:Description')
        );
    }

    public function testPaymentTermsOmitDueDateWhenInvoiceHasNoDueDate(): void
    {
        $xpath = $this->buildPaymentTermsXpath('2026-09-30', null);

        $nodes = $xpath->query(self::PAYMENT_TERMS . '/ram:DueDateDateTime');
        $this->assertNotFalse($nodes);
        $this->assertSame(0, $nodes->length);
    }

    private function buildPaymentTermsXpath(string $date, ?string $dueDate): DOMXPath
    {
        $this->invoice->date = $date;
        $this->invoice->due_date = $dueDate;
        $this->invoice->save();

        $xml = (new ZugferdEDocument($this->invoice->fresh()))->run()->getXml();

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
