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

namespace Tests\Integration\Einvoice;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Country;
use App\Models\Product;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ZugferdXmlValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private string $zugferd_xsd = '/Services/EDocument/Standards/Validation/Zugferd/Schema/XSD/CrossIndustryInvoice_100pD22B.xsd';

    private string $zug_16931 = 'Services/EDocument/Standards/Validation/Zugferd/zugferd_16931.xslt';

    private string $zf_extended = 'Services/EDocument/Standards/Validation/Zugferd/FACTUR-X_EXTENDED.xslt';

    private string $xrechnung_cii = 'Services/EDocument/Standards/Validation/Zugferd/xrechnung_cii.xslt';

    protected function setUp(): void
    {
        parent::setUp();

        if (config('ninja.testvars.travis')) {
            $this->markTestSkipped('do not run in CI');
        }

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->makeTestData();
    }

    /**
     * Gate: skip any test that requires Saxon if the extension is not loaded.
     */
    private function requireSaxon(): void
    {
        try {
            new \Saxon\SaxonProcessor();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Saxon processor not installed – skipping XSLT validation');
        }
    }

    /**
     * Run both XSD and XSLT validation on the given XML and assert zero errors.
     */
    private function assertXmlValid(string $xml, string $stylesheet, string $context = ''): void
    {
        $validator = new XsltDocumentValidator($xml);
        $validator->setStyleSheets([$stylesheet]);
        $validator->setXsd($this->zugferd_xsd);
        $validator->validate();

        if (count($validator->getErrors()) > 0) {
            nlog("Validation errors for: {$context}");
            nlog($xml);
            nlog($validator->getErrors());
        }

        $this->assertCount(0, $validator->getErrors(), "XML validation failed for: {$context}");
    }

    /**
     * Build a test scenario with company, client, invoice.
     */
    private function setupTestData(array $params = []): array
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE923356489';
        $settings->id_number = $params['company_id_number'] ?? '';
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = Country::where('iso_3166_2', $params['company_country'] ?? 'DE')->first()->id;
        $settings->email = $this->faker->safeEmail();
        $settings->e_invoice_type = $params['e_invoice_type'] ?? 'XInvoice_3_0';
        $settings->currency_id = '3';
        $settings->name = 'Test Company';
        $settings->address1 = 'Line 1 of address of the seller';
        $settings->city = 'Hamburg';
        $settings->postal_code = 'X123433';
        $settings->enable_e_invoice = true;

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? true;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = $params['company_country'] ?? 'DE';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = "DEUTDEMMXXX";

        $pfa = new \InvoiceNinja\EInvoice\Models\Peppol\FinancialAccountType\PayeeFinancialAccount();
        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $id->value = 'DE89370400440532013000';
        $pfa->ID = $id;
        $pfa->Name = 'PFA-NAME';
        $pfa->FinancialInstitutionBranch = $fib;

        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
        $pm->PayeeFinancialAccount = $pfa;

        $pmc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\PaymentMeansCode();
        $pmc->value = '30';
        $pm->PaymentMeansCode = $pmc;

        $einvoice->PaymentMeans[] = $pm;

        $stub = new \stdClass();
        $stub->Invoice = $einvoice;

        $this->company->settings = $settings;
        $this->company->tax_data = $tax_data;
        $this->company->calculate_taxes = true;
        $this->company->legal_entity_id = $params['legal_entity_id'] ?? 290868;
        $this->company->e_invoice = $stub;
        $this->company->save();

        $client_country_id = Country::where('iso_3166_2', $params['client_country'] ?? 'DE')->first()->id;

        $client_data = [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => $client_country_id,
            'vat_number' => $params['client_vat'] ?? '',
            'classification' => $params['classification'] ?? 'individual',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? false,
            'name' => 'Test Client',
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
            'address1' => 'Client Street 1',
            'city' => 'Berlin',
            'postal_code' => '10115',
        ];

        if (!empty($params['shipping_country'])) {
            $client_data['shipping_address1'] = $params['shipping_address1'] ?? 'Shipping Street 1';
            $client_data['shipping_city'] = $params['shipping_city'] ?? 'Shipping City';
            $client_data['shipping_postal_code'] = $params['shipping_postal_code'] ?? '00000';
            $client_data['shipping_country_id'] = Country::where('iso_3166_2', $params['shipping_country'])->first()->id;
        }

        $client = Client::factory()->create($client_data);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
        ]);

        $invoice = \App\Models\Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'uses_inclusive_taxes' => $params['uses_inclusive_taxes'] ?? false,
            'tax_rate1' => 0,
            'tax_name1' => '',
            'tax_rate2' => 0,
            'tax_name2' => '',
            'tax_rate3' => 0,
            'tax_name3' => '',
        ]);

        $items = $invoice->line_items;
        foreach ($items as &$item) {
            $item->tax_name2 = '';
            $item->tax_rate2 = 0;
            $item->tax_name3 = '';
            $item->tax_rate3 = 0;
        }
        unset($item);

        $invoice->line_items = array_values($items);
        $invoice = $invoice->calc()->getInvoice();

        return compact('client', 'invoice');
    }

    /**
     * Build line items with the given tax configuration.
     */
    private function createTaxedLineItems(array $tax_config, int $count = 2): array
    {
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $item = new InvoiceItem();
            $item->product_key = "Product " . ($i + 1);
            $item->notes = "Description for product " . ($i + 1);
            $item->quantity = $i + 1;
            $item->cost = round(100 + ($i * 50.25), 2);
            $item->tax_name1 = $tax_config['tax_name1'] ?? '';
            $item->tax_rate1 = $tax_config['tax_rate1'] ?? 0;
            $item->tax_id = $tax_config['tax_id'] ?? '';
            $item->type_id = $tax_config['type_id'] ?? '1';
            $item->discount = $tax_config['line_discount'] ?? 0;
            $item->is_amount_discount = $tax_config['is_amount_discount'] ?? false;
            $items[] = $item;
        }

        return $items;
    }

    // =========================================================================
    // XSD-only validation tests (no Saxon required)
    // =========================================================================

    public function testXsdValidationDeToDeStandardTax(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('CrossIndustryInvoice', $xml);

        // XSD-only validation (no Saxon needed)
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $valid = $dom->schemaValidate(app_path($this->zugferd_xsd));
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (!$valid) {
            nlog("XSD errors for DE-to-DE standard tax:");
            foreach ($errors as $error) {
                nlog(sprintf('Line %d: %s', $error->line, trim($error->message)));
            }
        }

        $this->assertTrue($valid, 'XSD validation failed for DE-to-DE standard tax');
    }

    public function testXsdValidationDeToNonEuExport(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $this->assertNotEmpty($xml);

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $valid = $dom->schemaValidate(app_path($this->zugferd_xsd));
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $this->assertTrue($valid, 'XSD validation failed for DE-to-US export');
    }

    // =========================================================================
    // Full XSLT + XSD validation — EN 16931 profile
    // =========================================================================

    public function testValidationDeToDeStandardTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-DE standard 19% tax');
    }

    public function testValidationDeToDeReducedTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt ermäßigt',
            'tax_rate1' => 7,
            'tax_id' => (string) Product::PRODUCT_TYPE_REDUCED_TAX,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-DE reduced 7% tax');
    }

    public function testValidationDeToDeInclusiveTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'uses_inclusive_taxes' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-DE inclusive 19% tax');
    }

    public function testValidationDeToDeTaxExempt(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'VAT',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-DE tax exempt');
    }

    public function testValidationClientTaxExempt(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'is_tax_exempt' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'VAT',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-DE client tax exempt');
    }

    // =========================================================================
    // Cross-border scenarios
    // =========================================================================

    public function testValidationDeToNlReverseTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_REVERSE_TAX,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-NL reverse charge');
    }

    public function testValidationDeToUsExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-US export (non-EU)');
    }

    public function testValidationDeToAuExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'AU',
            'client_vat' => '',
            'classification' => 'individual',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-AU export (non-EU individual)');
    }

    public function testValidationDeToGbExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'GB',
            'client_vat' => 'GB123456789',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-GB export (post-Brexit)');
    }

    // =========================================================================
    // Discount scenarios
    // =========================================================================

    public function testValidationWithAmountDiscountOnDocument(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->discount = 25;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'document-level amount discount');
    }

    public function testValidationWithPercentDiscountOnDocument(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->discount = 10;
        $invoice->is_amount_discount = false;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'document-level percent discount');
    }

    public function testValidationWithLineItemAmountDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
            'line_discount' => 10,
            'is_amount_discount' => true,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'line item amount discount');
    }

    public function testValidationWithLineItemPercentDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
            'line_discount' => 15,
            'is_amount_discount' => false,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'line item percent discount');
    }

    public function testValidationWithDocumentAndLineDiscountsCombined(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
            'line_discount' => 5,
            'is_amount_discount' => true,
        ]);
        $invoice->discount = 10;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'document + line item discounts combined');
    }

    // =========================================================================
    // Inclusive tax + discount scenarios
    // =========================================================================

    public function testValidationInclusiveTaxWithAmountDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'uses_inclusive_taxes' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->discount = 20;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'inclusive tax + amount discount');
    }

    public function testValidationInclusiveTaxWithPercentDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'uses_inclusive_taxes' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->discount = 15;
        $invoice->is_amount_discount = false;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'inclusive tax + percent discount');
    }

    public function testValidationInclusiveTaxWithLineAndDocDiscounts(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'uses_inclusive_taxes' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
            'line_discount' => 8,
            'is_amount_discount' => false,
        ]);
        $invoice->discount = 12;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'inclusive tax + line + doc discounts');
    }

    // =========================================================================
    // Surcharge scenarios
    // =========================================================================

    public function testValidationWithSurcharge(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->custom_surcharge1 = 25.50;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'single surcharge');
    }

    public function testValidationWithMultipleSurcharges(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->custom_surcharge1 = 10.00;
        $invoice->custom_surcharge2 = 15.00;
        $invoice->custom_surcharge3 = 5.50;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'multiple surcharges');
    }

    public function testValidationInclusiveTaxWithSurchargesAndDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'uses_inclusive_taxes' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->custom_surcharge1 = 20.00;
        $invoice->custom_surcharge2 = 10.00;
        $invoice->discount = 15;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'inclusive tax + surcharges + discount');
    }

    // =========================================================================
    // Public notes / document notes
    // =========================================================================

    public function testValidationWithPublicNotes(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->public_notes = 'This is a public note with special chars: äöü ß € & < >';
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        // Verify the note is present in XML
        $this->assertStringContainsString('IncludedNote', $xml);

        $this->assertXmlValid($xml, $this->zug_16931, 'invoice with public notes (special chars)');
    }

    public function testValidationWithoutPublicNotes(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->public_notes = '';
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'invoice without public notes');
    }

    // =========================================================================
    // Service / type_id scenarios
    // =========================================================================

    public function testValidationServiceLineItems(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_SERVICE,
            'type_id' => '2', // service => HUR unit
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'service line items (HUR)');
    }

    // =========================================================================
    // Partial payment scenario
    // =========================================================================

    public function testValidationPartiallyPaidInvoice(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();
        // Simulate partial payment
        $invoice->balance = round($invoice->amount / 2, 2);
        $invoice->save();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'partially paid invoice');
    }

    // =========================================================================
    // Shipping address / delivery
    // =========================================================================

    public function testValidationWithShippingAddress(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $client = $data['client'];
        $client->shipping_address1 = 'Shipping Street 42';
        $client->shipping_city = 'Munich';
        $client->shipping_postal_code = '80331';
        $client->shipping_country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $client->save();

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'invoice with shipping address');
    }

    // =========================================================================
    // PO number / buyer reference
    // =========================================================================

    public function testValidationWithPoNumber(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->po_number = 'PO-2026-001234';
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'invoice with PO number');
    }

    public function testValidationWithRoutingId(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $client = $data['client'];
        $client->routing_id = '04011000-12345-67';
        $client->save();

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'invoice with routing ID (Leitweg-ID)');
    }

    // =========================================================================
    // Extended profile validation
    // =========================================================================

    public function testValidationExtendedProfileDeToDeStandardTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'e_invoice_type' => 'XInvoice-Extended',
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zf_extended, 'Extended: DE-to-DE standard 19% tax');
    }

    public function testValidationExtendedProfileDeToNlReverseTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
            'e_invoice_type' => 'XInvoice-Extended',
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_REVERSE_TAX,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zf_extended, 'Extended: DE-to-NL reverse charge');
    }

    public function testValidationExtendedProfileExportNonEu(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
            'e_invoice_type' => 'XInvoice-Extended',
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zf_extended, 'Extended: DE-to-US export');
    }

    public function testValidationExtendedProfileInclusiveTaxWithDiscounts(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'uses_inclusive_taxes' => true,
            'e_invoice_type' => 'XInvoice-Extended',
        ]);

        $invoice = $data['invoice'];
        $invoice->uses_inclusive_taxes = true;
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
            'line_discount' => 5,
            'is_amount_discount' => true,
        ]);
        $invoice->discount = 10;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zf_extended, 'Extended: inclusive tax + discounts');
    }

    // =========================================================================
    // XRechnung CII profile validation
    // =========================================================================

    public function testValidationXRechnungDeToDeStandardTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'e_invoice_type' => 'XInvoice_3_0',
        ]);

        $client = $data['client'];
        $client->routing_id = '04011000-12345-67';
        $client->save();

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->xrechnung_cii, 'XRechnung CII: DE-to-DE standard tax');
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testValidationSingleLineItem(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ], 1);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'single line item');
    }

    public function testValidationManyLineItems(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ], 10);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, '10 line items');
    }

    public function testValidationHighValueInvoice(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];

        $item = new InvoiceItem();
        $item->product_key = "Enterprise License";
        $item->notes = "Annual enterprise license";
        $item->quantity = 1;
        $item->cost = 99999.99;
        $item->tax_name1 = 'MwSt';
        $item->tax_rate1 = 19;
        $item->tax_id = (string) Product::PRODUCT_TYPE_DIGITAL;
        $item->type_id = '1';

        $invoice->line_items = [$item];
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'high value invoice');
    }

    public function testValidationNoPaymentTermsWithNullDueDate(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->due_date = null;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'invoice with null due_date');
    }

    // =========================================================================
    // Payment terms uniqueness (regression: duplicate payment terms)
    // =========================================================================

    public function testPaymentTermsAppearOnlyOnce(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'MwSt',
            'tax_rate1' => 19,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $count = substr_count($xml, 'SpecifiedTradePaymentTerms');
        // Opening + closing tag = 2 occurrences for a single element
        $this->assertEquals(2, $count, "SpecifiedTradePaymentTerms should appear exactly once (open+close). Found {$count} occurrences.");
    }

    // =========================================================================
    // Exemption reason code presence (regression: missing $this-> prefix)
    // =========================================================================

    public function testExportExemptionReasonCodePresent(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        // VATEX-EU-G must be present for non-EU exports
        $this->assertStringContainsString('VATEX-EU-G', $xml, 'Export to non-EU must contain VATEX-EU-G exemption reason code');
    }

    public function testReverseChargeExemptionReasonCodePresent(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_REVERSE_TAX,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $this->assertStringContainsString('VATEX-EU-AE', $xml, 'Reverse charge must contain VATEX-EU-AE exemption reason code');
    }

    public function testIntraCommunityExemptionReasonCodePresent(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'FR',
            'client_vat' => 'FR12345678901',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => '10', // Intra-community
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $this->assertStringContainsString('VATEX-EU-IC', $xml, 'Intra-community must contain VATEX-EU-IC exemption reason code');
    }

    // =========================================================================
    // AT (Austria) company scenarios
    // =========================================================================

    public function testValidationAtToAtStandardTax(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'ATU12345678',
            'company_country' => 'AT',
            'client_country' => 'AT',
            'client_vat' => 'ATU87654321',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'USt',
            'tax_rate1' => 20,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'AT-to-AT standard 20% tax');
    }

    public function testValidationAtToUsExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'ATU12345678',
            'company_country' => 'AT',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'AT-to-US export');
    }

    // =========================================================================
    // Additional edge-case tests for export / cross-border fixes
    // =========================================================================

    public function testValidationDeToUsExportWithDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->discount = 15;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-US export with amount discount');
    }

    public function testValidationDeToUsExportWithPercentDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->discount = 10;
        $invoice->is_amount_discount = false;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-US export with percent discount');
    }

    public function testValidationDeToUsExportWithSurcharge(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->custom_surcharge1 = 30.00;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-US export with surcharge');
    }

    public function testValidationDeToUsExportWithLineDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
            'line_discount' => 10,
            'is_amount_discount' => true,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-US export with line discount');
    }

    public function testValidationDeToJpExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'JP',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-JP export');
    }

    public function testValidationDeToChExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'CH',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-CH export (EFTA, non-EU)');
    }

    // =========================================================================
    // Intra-community XSLT validation
    // =========================================================================

    public function testValidationDeToFrIntraCommunity(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'FR',
            'client_vat' => 'FR12345678901',
            'classification' => 'business',
            'has_valid_vat' => true,
            'shipping_country' => 'FR', // BR-IC-12: intra-community requires delivery-to country
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => '10', // Intra-community
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-FR intra-community');
    }

    public function testValidationDeToItIntraCommunity(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'IT',
            'client_vat' => 'IT12345678901',
            'classification' => 'business',
            'has_valid_vat' => true,
            'shipping_country' => 'IT',
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => '10',
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-IT intra-community');
    }

    public function testValidationDeToEsIntraCommunityWithDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'ES',
            'client_vat' => 'ESA12345678',
            'classification' => 'business',
            'has_valid_vat' => true,
            'shipping_country' => 'ES',
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => '10',
        ]);
        $invoice->discount = 20;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'DE-to-ES intra-community with discount');
    }

    // =========================================================================
    // Reverse charge with discounts/surcharges
    // =========================================================================

    public function testValidationReverseChargeWithDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_REVERSE_TAX,
        ]);
        $invoice->discount = 10;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'reverse charge with amount discount');
    }

    public function testValidationReverseChargeWithSurcharge(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_REVERSE_TAX,
        ]);
        $invoice->custom_surcharge1 = 15.00;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'reverse charge with surcharge');
    }

    // =========================================================================
    // Extended profile: export + discount combinations
    // =========================================================================

    public function testValidationExtendedProfileExportWithDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
            'e_invoice_type' => 'XInvoice-Extended',
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->discount = 25;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zf_extended, 'Extended: export with discount');
    }

    public function testValidationExtendedProfileIntraCommunity(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'FR',
            'client_vat' => 'FR12345678901',
            'classification' => 'business',
            'has_valid_vat' => true,
            'e_invoice_type' => 'XInvoice-Extended',
            'shipping_country' => 'FR',
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => '10',
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zf_extended, 'Extended: DE-to-FR intra-community');
    }

    // =========================================================================
    // Tax exempt with discounts/surcharges
    // =========================================================================

    public function testValidationTaxExemptWithDiscount(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'VAT',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
        ]);
        $invoice->discount = 10;
        $invoice->is_amount_discount = true;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'tax exempt with amount discount');
    }

    public function testValidationTaxExemptWithSurcharge(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'VAT',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
        ]);
        $invoice->custom_surcharge1 = 20.00;
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'tax exempt with surcharge');
    }

    // =========================================================================
    // Export exemption code in XML content assertions
    // =========================================================================

    public function testExportToGbHasVatexEuG(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'GB',
            'client_vat' => 'GB123456789',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $this->assertStringContainsString('VATEX-EU-G', $xml, 'GB export must have VATEX-EU-G');
        // Category G = FREE_EXPORT_ITEM_TAX_NOT_CHARGED
        $this->assertStringContainsString('>G<', $xml, 'GB export must use tax category G');
    }

    public function testExportLineItemsTaxCategoryMatchesDocument(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        // Must NOT contain category E (EXEMPT_FROM_TAX) — that was the old bug
        // The document should only use category G for exports
        $this->assertStringNotContainsString('>E</', $xml, 'Export should not use EXEMPT_FROM_TAX (E) category');
    }

    public function testClientTaxExemptExemptionCodePresent(): void
    {
        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'DE',
            'client_vat' => 'DE923356488',
            'classification' => 'business',
            'has_valid_vat' => true,
            'is_tax_exempt' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => 'VAT',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_EXEMPT,
        ]);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $this->assertStringContainsString('VATEX-EU-O', $xml, 'Client-exempt should have VATEX-EU-O');
    }

    // =========================================================================
    // Export with public notes combined
    // =========================================================================

    public function testValidationExportWithPublicNotes(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice->public_notes = 'Export shipment — no VAT applicable';
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();

        $this->assertStringContainsString('IncludedNote', $xml);
        $this->assertXmlValid($xml, $this->zug_16931, 'export with public notes');
    }

    // =========================================================================
    // Single line item edge cases for each tax scenario
    // =========================================================================

    public function testValidationSingleLineItemExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ], 1);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'single line item export');
    }

    public function testValidationSingleLineItemReverseCharge(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'NL',
            'client_vat' => 'NL808436332B01',
            'classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_REVERSE_TAX,
        ], 1);
        $invoice = $invoice->calc()->getInvoice();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'single line item reverse charge');
    }

    public function testValidationPartiallyPaidExport(): void
    {
        $this->requireSaxon();

        $data = $this->setupTestData([
            'company_vat' => 'DE923356489',
            'company_country' => 'DE',
            'client_country' => 'US',
            'client_vat' => '',
            'classification' => 'business',
            'has_valid_vat' => false,
        ]);

        $invoice = $data['invoice'];
        $invoice->line_items = $this->createTaxedLineItems([
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_id' => (string) Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->balance = round($invoice->amount / 2, 2);
        $invoice->save();

        $xml = $invoice->service()->getEInvoice();
        $this->assertXmlValid($xml, $this->zug_16931, 'partially paid export');
    }
}
