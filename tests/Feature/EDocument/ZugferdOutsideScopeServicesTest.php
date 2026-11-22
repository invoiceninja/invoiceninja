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
use Mockery;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Currency;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\CompanySettings;
use App\Exceptions\PeppolValidationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\Attributes\DataProvider;
use App\Services\EDocument\Standards\ZugferdEDocument;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;

/**
 * Exercise the public exporter and real invoice calculators with unsaved models.
 * Only the contact query and attachment entitlement are stubbed; tax resolution,
 * party identifiers, adjustments, totals, and XML serialization run unchanged.
 * Fixtures represent ordinary B2B services under the general place-of-supply rule.
 */
class ZugferdOutsideScopeServicesTest extends TestCase
{
    private const RAM_NAMESPACE = 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100';

    private const HEADER = '//ram:ApplicableHeaderTradeSettlement';

    private const LINE_TAX = '//ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax';

    #[DataProvider('outsideScopeServices')]
    public function testNonEuBusinessServicesUseOutsideScopeCategory(
        array $tax_ids,
        string $tax_name,
        string $client_country,
        bool $is_credit,
        bool $uses_inclusive_taxes,
        string $profile
    ): void {
        $this->requireSaxon();

        $document = $this->makeDocument(
            array_map(fn (int $tax_id): InvoiceItem => $this->lineItem($tax_id, $tax_name), $tax_ids),
            $client_country,
            is_credit: $is_credit,
            uses_inclusive_taxes: $uses_inclusive_taxes,
            company_settings: ['e_invoice_type' => $profile]
        );
        $xml = $this->exportXml($document);
        $xpath = $this->xpath($xml);

        $this->assertSame(array_fill(0, count($tax_ids), 'O'), $this->values($xpath, self::LINE_TAX . '/ram:CategoryCode'));
        $this->assertSame(['O'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:CategoryCode'));
        $this->assertSame(['VATEX-EU-O'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:ExemptionReasonCode'));
        $this->assertSame(['0.00'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:CalculatedAmount'));
        $this->assertSame([$is_credit ? '381' : '380'], $this->values($xpath, '//rsm:ExchangedDocument/ram:TypeCode'));
        $this->assertXmlValid($xml, $profile);
    }

    public static function outsideScopeServices(): iterable
    {
        yield 'US service without tax name' => [[2], '', 'US', false, false, 'EN16931'];
        yield 'US service with named zero tax' => [[2], 'VAT', 'US', false, false, 'EN16931'];
        yield 'GB digital product' => [[3], '', 'GB', false, false, 'EN16931'];
        yield 'CH digital product with named zero tax' => [[3], 'VAT', 'CH', false, false, 'EN16931'];
        yield 'NO service' => [[2], '', 'NO', false, false, 'EN16931'];
        yield 'services and digital products together' => [[2, 3], '', 'AU', false, false, 'EN16931'];
        yield 'service credit' => [[2], '', 'US', true, false, 'EN16931'];
        yield 'inclusive digital credit' => [[3], 'VAT', 'US', true, true, 'EN16931'];
        yield 'XRechnung service' => [[2], '', 'US', false, false, 'XInvoice_3_0'];
    }

    public function testOutsideScopeLineOmitsVatRateInsteadOfSerializingZero(): void
    {
        $xpath = $this->xpath($this->exportXml($this->makeDocument([$this->lineItem()])));

        $this->assertSame([], $this->values($xpath, self::LINE_TAX . '/ram:RateApplicablePercent'), 'BR-O-05 prohibits even a zero VAT rate on an outside-scope line.');
    }

    public function testOutsideScopeDocumentOmitsSellerAndBuyerVatIdentifiers(): void
    {
        $xpath = $this->xpath($this->exportXml($this->makeDocument([$this->lineItem()])));

        $this->assertSame([], $this->values($xpath, '//ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'), 'BR-O-02 prohibits seller, buyer, and tax representative VAT identifiers.');
    }

    public function testOutsideScopeSellerHasConfiguredIdentifierWithoutRequiringSlashes(): void
    {
        $xpath = $this->xpath($this->exportXml($this->makeDocument([$this->lineItem()])));
        $seller = '//ram:SellerTradeParty';

        $this->assertContains('HRB 12345', $this->values($xpath, $seller . '/ram:ID | ' . $seller . '/ram:SpecifiedLegalOrganization/ram:ID'), 'BR-CO-26 requires BT-29 or BT-30 when BT-31 is omitted; FC alone is insufficient.');
    }

    public function testOutsideScopeSellerRetainsFiscalRegistrationAlongsideSellerIdentifier(): void
    {
        $document = $this->makeDocument([$this->lineItem()], company_settings: ['vat_number' => '123/456/78901']);
        $xpath = $this->xpath($this->exportXml($document));

        $this->assertSame(['123/456/78901'], $this->values($xpath, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="FC"]'));
        $this->assertContains('HRB 12345', $this->values($xpath, '//ram:SellerTradeParty/ram:ID | //ram:SellerTradeParty/ram:SpecifiedLegalOrganization/ram:ID'));
    }

    #[DataProvider('missingSellerIdentifiers')]
    public function testOutsideScopeSellerRequiresAnIdentifierOtherThanVatOrFiscalRegistration(string $vat_number): void
    {
        $document = $this->makeDocument([$this->lineItem()], company_settings: ['id_number' => '', 'vat_number' => $vat_number]);

        $this->assertExportRejected($document, 'settings.id_number');
    }

    public static function missingSellerIdentifiers(): iterable
    {
        yield 'VAT identifier cannot be retained for O' => ['DE123456789'];
        yield 'FC tax number alone cannot satisfy BR-CO-26' => ['123/456/78901'];
    }

    public function testFiscalNumberIsNotMisrepresentedAsAGlobalLocationNumber(): void
    {
        $document = $this->makeDocument([$this->lineItem()], company_settings: ['id_number' => '123/456/78901']);
        $xpath = $this->xpath($this->exportXml($document));

        $this->assertSame([], $this->values($xpath, '//ram:SellerTradeParty/ram:GlobalID[@schemeID="0088"]'), 'A fiscal number is not a GS1 GLN.');
        $this->assertSame(['123/456/78901'], $this->values($xpath, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="FC"]'));
    }

    #[DataProvider('documentAdjustments')]
    public function testOutsideScopeDiscountAndSurchargeUseOneConsistentBreakdown(bool $taxable_surcharge, bool $is_credit, bool $uses_inclusive_taxes): void
    {
        $this->requireSaxon();

        $document = $this->makeDocument(
            [$this->lineItem()],
            is_credit: $is_credit,
            uses_inclusive_taxes: $uses_inclusive_taxes,
            document_attributes: [
                'discount' => 10,
                'is_amount_discount' => true,
                'custom_surcharge1' => 5,
                'custom_surcharge_tax1' => $taxable_surcharge,
            ]
        );
        $xml = $this->exportXml($document);
        $xpath = $this->xpath($xml);
        $adjustments = self::HEADER . '/ram:SpecifiedTradeAllowanceCharge';
        $summation = self::HEADER . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation';

        $this->assertSame(['O', 'O'], $this->values($xpath, $adjustments . '/ram:CategoryTradeTax/ram:CategoryCode'));
        $this->assertSame([], $this->values($xpath, $adjustments . '/ram:CategoryTradeTax/ram:RateApplicablePercent'), 'BR-O-06 and BR-O-07 prohibit VAT rates on O adjustments.');
        $this->assertSame(['O'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:CategoryCode'));
        $this->assertSame(['95.00'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:BasisAmount'));
        $this->assertSame(['100.00'], $this->values($xpath, $summation . '/ram:LineTotalAmount'));
        $this->assertSame(['10.00'], $this->values($xpath, $summation . '/ram:AllowanceTotalAmount'));
        $this->assertSame(['5.00'], $this->values($xpath, $summation . '/ram:ChargeTotalAmount'));
        $this->assertSame(['95.00'], $this->values($xpath, $summation . '/ram:TaxBasisTotalAmount'));
        $this->assertSame(['0.00'], $this->values($xpath, $summation . '/ram:TaxTotalAmount'));
        $this->assertSame(['95.00'], $this->values($xpath, $summation . '/ram:GrandTotalAmount'));
        $this->assertXmlValid($xml);
    }

    public static function documentAdjustments(): iterable
    {
        yield 'untaxed surcharge' => [false, false, false];
        yield 'taxable surcharge on zero VAT services' => [true, false, false];
        yield 'inclusive invoice' => [false, false, true];
        yield 'credit' => [false, true, false];
    }

    public function testFullyDiscountedOutsideScopeServicesRetainTheirZeroAmountBreakdown(): void
    {
        $this->requireSaxon();

        $document = $this->makeDocument([$this->lineItem()], document_attributes: ['discount' => 100]);
        $xml = $this->exportXml($document);
        $xpath = $this->xpath($xml);

        $this->assertSame(['O'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:CategoryCode'), 'BR-O-01 still requires one O breakdown when its basis is zero.');
        $this->assertSame(['0.00'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:BasisAmount'));
        $this->assertSame(['0.00'], $this->values($xpath, self::HEADER . '/ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:GrandTotalAmount'));
        $this->assertXmlValid($xml);
    }

    #[DataProvider('mixedCategories')]
    public function testOutsideScopeServicesCannotBeSilentlyExportedWithOtherVatCategories(array $tax_ids, string $tax_name): void
    {
        $document = $this->makeDocument(array_map(fn (int $tax_id): InvoiceItem => $this->lineItem($tax_id, $tax_name), $tax_ids));

        $this->assertExportRejected($document, 'line_items.tax_id');
    }

    public static function mixedCategories(): iterable
    {
        yield 'service then goods' => [[2, 1], ''];
        yield 'goods then service' => [[1, 2], ''];
        yield 'named zero tax service and goods' => [[2, 1], 'VAT'];
        yield 'service and explicit exemption' => [[2, 5], ''];
        yield 'service and explicit zero rating' => [[2, 8], ''];
        yield 'service and explicit reverse charge' => [[2, 9], ''];
    }

    #[DataProvider('ambiguousServices')]
    public function testAmbiguousZeroTaxLinesRequireExplicitTreatment(?string $tax_id, string $classification): void
    {
        $item = $this->lineItem();
        $item->tax_id = $tax_id;
        $document = $this->makeDocument([$item], client_attributes: ['classification' => $classification]);

        $this->assertExportRejected($document, 'line_items.tax_id');
    }

    public static function ambiguousServices(): iterable
    {
        yield 'ordinary B2C service needs its own tax treatment' => ['2', 'individual'];
        yield 'unknown customer classification is not assumed B2B' => ['2', ''];
    }

    #[DataProvider('unclassifiedLines')]
    public function testUnclassifiedInvoicesAreNotAssumedToBeOutsideScopeServices(?string $tax_id): void
    {
        $item = $this->lineItem();
        $item->tax_id = $tax_id;
        $xpath = $this->xpath($this->exportXml($this->makeDocument([$item])));

        $this->assertNotContains('O', $this->values($xpath, '//ram:CategoryCode'));
        $this->assertNotContains('VATEX-EU-O', $this->values($xpath, '//ram:ExemptionReasonCode'));
    }

    public static function unclassifiedLines(): iterable
    {
        yield 'blank tax ID' => [''];
        yield 'null tax ID' => [null];
        yield 'unknown tax ID' => ['999'];
    }

    public function testExemptLinesDoNotInheritExportOrOutsideScopeReasonCodes(): void
    {
        $this->requireSaxon();

        $xml = $this->exportXml($this->makeDocument([$this->lineItem(Product::PRODUCT_TYPE_EXEMPT)]));
        $xpath = $this->xpath($xml);

        $this->assertSame(['E'], $this->values($xpath, self::LINE_TAX . '/ram:CategoryCode'));
        $this->assertSame(['E'], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:CategoryCode'));
        $this->assertNotContains('VATEX-EU-G', $this->values($xpath, '//ram:ExemptionReasonCode'));
        $this->assertNotContains('VATEX-EU-O', $this->values($xpath, '//ram:ExemptionReasonCode'));
        $this->assertXmlValid($xml);
    }

    #[DataProvider('chargedTaxSlots')]
    public function testChargedTaxOutsideTheFirstLineSlotCannotDisappearIntoOutsideScope(int $tax_slot): void
    {
        $this->requireSaxon();

        $item = $this->lineItem();
        $document_attributes = [];
        if ($tax_slot === 0) {
            $document_attributes = ['tax_name1' => 'VAT', 'tax_rate1' => 5];
        } else {
            $item->{"tax_name{$tax_slot}"} = 'VAT';
            $item->{"tax_rate{$tax_slot}"} = 5;
        }
        $document = $this->makeDocument([$item], document_attributes: $document_attributes);
        $expected_tax = (float) $document->total_taxes;
        $this->assertSame(5.0, $expected_tax);

        try {
            $xml = $this->exportXml($document);
        } catch (PeppolValidationException|ValidationException $exception) {
            // Rejecting an unsupported tax configuration is also safe.
            $this->assertNotSame('', $exception->getMessage());

            return;
        }

        $xpath = $this->xpath($xml);
        $this->assertNotContains('O', $this->values($xpath, '//ram:CategoryCode'));
        $this->assertEqualsWithDelta($expected_tax, array_sum($this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:CalculatedAmount')), 0.001);
        $this->assertXmlValid($xml);
    }

    public static function chargedTaxSlots(): iterable
    {
        yield 'second line tax' => [2];
        yield 'third line tax' => [3];
        yield 'document tax' => [0];
    }

    public function testUnusedDocumentTaxRatesDoNotBlockOutsideScopeServices(): void
    {
        $this->requireSaxon();

        $document = $this->makeDocument([$this->lineItem()], document_attributes: ['tax_name1' => '', 'tax_rate1' => 19]);
        $this->assertSame(0.0, (float) $document->total_taxes);
        $xml = $this->exportXml($document);

        $this->assertSame(['O'], $this->values($this->xpath($xml), self::LINE_TAX . '/ram:CategoryCode'));
        $this->assertXmlValid($xml);
    }

    public function testOutsideScopeAndTaxedServicesRequireSeparateInvoices(): void
    {
        $document = $this->makeDocument([$this->lineItem(), $this->lineItem(Product::PRODUCT_TYPE_SERVICE, 'VAT', 19)]);

        $this->assertExportRejected($document, 'line_items.tax_id');
    }

    public function testUnclassifiedLineCannotBeFoldedIntoOutsideScopeServices(): void
    {
        $item = $this->lineItem();
        $item->tax_id = '';
        $document = $this->makeDocument([$this->lineItem(), $item]);

        $this->assertExportRejected($document, 'line_items.tax_id');
    }

    #[DataProvider('preservedTaxTreatments')]
    public function testRemediationPreservesGoodsExportsChargedVatAndExplicitCategories(
        int $tax_id,
        string $tax_name,
        float $tax_rate,
        string $seller_country,
        string $client_country,
        string $classification,
        string $expected_category,
        ?string $expected_reason
    ): void {
        $this->requireSaxon();

        $document = $this->makeDocument(
            [$this->lineItem($tax_id, $tax_name, $tax_rate)],
            $client_country,
            $seller_country,
            client_attributes: ['classification' => $classification]
        );
        $xml = $this->exportXml($document);
        $xpath = $this->xpath($xml);

        $this->assertSame([$expected_category], $this->values($xpath, self::LINE_TAX . '/ram:CategoryCode'));
        $this->assertSame([$expected_category], $this->values($xpath, self::HEADER . '/ram:ApplicableTradeTax/ram:CategoryCode'));
        $this->assertSame($expected_reason === null ? [] : [$expected_reason], $this->values($xpath, '//ram:ExemptionReasonCode'));
        $this->assertNotEmpty($this->values($xpath, '//ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[@schemeID="VA"]'));
        $this->assertXmlValid($xml);
    }

    public static function preservedTaxTreatments(): iterable
    {
        yield 'physical export to US' => [1, '', 0, 'DE', 'US', 'business', 'G', 'VATEX-EU-G'];
        yield 'physical export to CH' => [1, '', 0, 'DE', 'CH', 'business', 'G', 'VATEX-EU-G'];
        yield 'domestic taxed service' => [2, 'VAT', 19, 'DE', 'DE', 'business', 'S', null];
        yield 'non-EU service with charged VAT' => [2, 'VAT', 19, 'DE', 'US', 'business', 'S', null];
        yield 'EU service with charged VAT' => [2, 'VAT', 19, 'DE', 'FR', 'individual', 'S', null];
        yield 'non-EU consumer with charged VAT' => [2, 'VAT', 19, 'DE', 'US', 'individual', 'S', null];
        yield 'non-EU seller with domestic tax' => [2, 'Tax', 10, 'US', 'US', 'business', 'S', null];
        yield 'explicit reverse charge' => [9, '', 0, 'DE', 'US', 'business', 'AE', 'VATEX-EU-AE'];
        yield 'explicit zero rating' => [8, '', 0, 'DE', 'US', 'business', 'Z', null];
    }

    private function lineItem(int $tax_id = Product::PRODUCT_TYPE_SERVICE, string $tax_name = '', float $tax_rate = 0): InvoiceItem
    {
        $item = new InvoiceItem();
        $item->product_key = match ($tax_id) {
            Product::PRODUCT_TYPE_SERVICE => 'Professional services',
            Product::PRODUCT_TYPE_DIGITAL => 'Software subscription',
            Product::PRODUCT_TYPE_PHYSICAL => 'Exported goods',
            default => 'Invoice item',
        };
        $item->notes = 'Description for ' . $item->product_key;
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_id = (string) $tax_id;
        $item->tax_name1 = $tax_name;
        $item->tax_rate1 = $tax_rate;

        return $item;
    }

    private function makeDocument(
        array $line_items,
        string $client_country = 'US',
        string $seller_country = 'DE',
        bool $is_credit = false,
        bool $uses_inclusive_taxes = false,
        array $document_attributes = [],
        array $company_settings = [],
        array $client_attributes = []
    ): Invoice|Credit {
        $seller = new Country();
        $seller->forceFill(['id' => 1, 'iso_3166_2' => $seller_country]);
        $buyer = new Country();
        $buyer->forceFill(['id' => 2, 'iso_3166_2' => $client_country]);
        $currency = new Currency();
        $currency->forceFill(['id' => 3, 'code' => 'EUR', 'precision' => 2]);
        $this->app->instance('countries', collect([$seller, $buyer]));
        $this->app->instance('currencies', collect([$currency]));

        $settings = CompanySettings::defaults();
        foreach (array_merge([
            'name' => 'Consulting GmbH',
            'address1' => 'Seller Street 1',
            'city' => 'Berlin',
            'postal_code' => '10115',
            'phone' => '+493012345678',
            'country_id' => 1,
            'currency_id' => 3,
            'vat_number' => $seller_country . '123456789',
            'id_number' => 'HRB 12345',
            'e_invoice_type' => 'EN16931',
        ], $company_settings) as $key => $value) {
            $settings->{$key} = $value;
        }

        $account = Mockery::mock(Account::class)->makePartial();
        $account->shouldReceive('hasFeature')->with(Account::FEATURE_DOCUMENTS)->andReturn(false);
        $company = new Company();
        $company->settings = $settings;
        $company->calculate_taxes = false;
        $company->setRelation('account', $account);

        $contact = new ClientContact();
        $contact->forceFill(['first_name' => 'Buyer', 'last_name' => 'Contact', 'email' => 'buyer@example.com']);
        $contact_query = Mockery::mock(HasMany::class);
        $contact_query->shouldReceive('first')->andReturn($contact);
        $client = Mockery::mock(Client::class)->makePartial();
        $client->shouldReceive('primary_contact')->andReturn($contact_query);
        $client->forceFill(array_merge([
            'name' => 'Overseas Customer',
            'number' => 'CLIENT-1',
            'address1' => 'Buyer Street 1',
            'city' => 'Buyer City',
            'postal_code' => '10001',
            'classification' => 'business',
            'vat_number' => $client_country . '987654321',
            'is_tax_exempt' => false,
        ], $client_attributes));
        $client->setRelation('country', $buyer);
        $client->setRelation('company', $company);
        $client->setRelation('group_settings', null);
        $client->setRelation('primary_contact', collect([$contact]));
        $client->setRelation('contacts', collect([$contact]));

        $user = new User();
        $user->forceFill(['first_name' => 'Seller', 'last_name' => 'Contact', 'email' => 'seller@example.com']);
        $document = $is_credit ? new Credit() : new Invoice();
        $document->forceFill(array_merge([
            'number' => 'OUTSIDE-SCOPE-1',
            'date' => '2026-11-23',
            'due_date' => '2026-12-23',
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 0,
            'balance' => 0,
            'paid_to_date' => 0,
            'discount' => 0,
            'is_amount_discount' => true,
            'uses_inclusive_taxes' => $uses_inclusive_taxes,
            'line_items' => $line_items,
        ], $document_attributes));
        $document->setRelation('company', $company);
        $document->setRelation('client', $client);
        $document->setRelation('user', $user);

        return $document->calc()->getTempEntity();
    }

    private function exportXml(Invoice|Credit $document): string
    {
        return (new ZugferdEDocument($document))->run()->getXml();
    }

    private function assertExportRejected(Invoice|Credit $document, string $field): void
    {
        try {
            $this->exportXml($document);
        } catch (PeppolValidationException $exception) {
            $this->assertSame($field, $exception->getInvalidField());
            $this->assertNotSame('', $exception->getMessage());

            return;
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());

            return;
        }

        $this->fail('Expected actionable validation for ' . $field . ' instead of silently emitting a misleading e-invoice.');
    }

    private function assertXmlValid(string $xml, string $profile = 'EN16931'): void
    {
        $this->assertTrue(class_exists(\Saxon\SaxonProcessor::class), 'Saxon is required to run EN 16931 business-rule validation.');

        $stylesheets = ['Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt'];
        if ($profile === 'XInvoice_3_0') {
            $stylesheets[] = 'Services/EDocument/Standards/Validation/Zugferd/xrechnung_cii.xslt';
        }

        $validator = new XsltDocumentValidator($xml);
        $validator->setStyleSheets($stylesheets);
        $validator->setXsd('Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd');
        $validator->validate();
        $errors = collect($validator->getErrors())->flatten()->all();

        $this->assertSame([], $errors, implode("\n", $errors));
    }

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ram', self::RAM_NAMESPACE);
        $xpath->registerNamespace('rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');

        return $xpath;
    }

    private function values(DOMXPath $xpath, string $expression): array
    {
        $nodes = $xpath->query($expression);
        $this->assertNotFalse($nodes);

        return array_map(static fn ($node): string => trim($node->textContent), iterator_to_array($nodes));
    }

    private function requireSaxon(): void
    {
        try {
            new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Saxon processor not installed – skipping XSLT validation');
        }
    }

}
