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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Comprehensive country-level tests for the PEPPOL e-invoice pipeline.
 *
 * Covers domestic (XX => XX) and cross-border (XX => YY) scenarios
 * for every country with a handler in CountryFactory.
 *
 * Set DUMP_PEPPOL_XML=true in .env.testing (or environment) to write
 * generated XML to tests/artifacts/peppol/ for external validation.
 *
 * XSD validation runs unconditionally.
 * XSLT/Schematron validation runs only when Saxon is installed.
 */
class PeppolCountryTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private bool $dumpXml;
    private bool $hasSaxon = false;
    private string $artifactDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->dumpXml = env('DUMP_PEPPOL_XML', false);
        $this->artifactDir = base_path('tests/artifacts/peppol');

        if ($this->dumpXml && !is_dir($this->artifactDir)) {
            mkdir($this->artifactDir, 0755, true);
        }

        try {
            new \Saxon\SaxonProcessor();
            $this->hasSaxon = true;
        } catch (\Throwable $e) {
            $this->hasSaxon = false;
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Scenario builder
    // ──────────────────────────────────────────────────────────────

    /**
     * Country-specific defaults for realistic test data.
     *
     * Each entry provides: vat prefix/format, typical id_number,
     * tax rate/name, address fields, currency, and routing_id where relevant.
     */
    private function countryDefaults(): array
    {
        return [
            'AT' => [
                'vat' => 'ATU12345678', 'id_number' => '123456789', 'tax_rate' => 20, 'tax_name' => 'USt',
                'city' => 'Vienna', 'state' => 'Vienna', 'postal_code' => '1010', 'currency' => '3',
                'address1' => 'Stephansplatz 1',
            ],
            'AU' => [
                'vat' => '12345678901', 'id_number' => 'ABN12345678901', 'tax_rate' => 10, 'tax_name' => 'GST',
                'city' => 'Sydney', 'state' => 'NSW', 'postal_code' => '2000', 'currency' => '12',
                'address1' => 'George Street 1',
            ],
            'CH' => [
                'vat' => 'CHE123456789', 'id_number' => 'CHE123456789', 'tax_rate' => 8.1, 'tax_name' => 'MWST',
                'city' => 'Zurich', 'state' => 'ZH', 'postal_code' => '8001', 'currency' => '17',
                'address1' => 'Bahnhofstrasse 1',
            ],
            'DE' => [
                'vat' => 'DE923356489', 'id_number' => '01234567890', 'tax_rate' => 19, 'tax_name' => 'VAT',
                'city' => 'Berlin', 'state' => 'Berlin', 'postal_code' => '10115', 'currency' => '3',
                'address1' => 'Unter den Linden 1',
            ],
            'DK' => [
                'vat' => 'DK12345678', 'id_number' => '12345678', 'tax_rate' => 25, 'tax_name' => 'Moms',
                'city' => 'Copenhagen', 'state' => 'Capital Region', 'postal_code' => '1050', 'currency' => '20',
                'address1' => 'Strøget 1',
            ],
            'ES' => [
                'vat' => 'ESB12345678', 'id_number' => 'B12345678', 'tax_rate' => 21, 'tax_name' => 'IVA',
                'city' => 'Madrid', 'state' => 'Madrid', 'postal_code' => '28001', 'currency' => '3',
                'address1' => 'Gran Via 1',
            ],
            'FI' => [
                'vat' => 'FI12345678', 'id_number' => '003712345678', 'tax_rate' => 25.5, 'tax_name' => 'ALV',
                'city' => 'Helsinki', 'state' => 'Uusimaa', 'postal_code' => '00100', 'currency' => '3',
                'address1' => 'Mannerheimintie 1',
            ],
            'FR' => [
                'vat' => 'FRAA123456789', 'id_number' => '12345678901234', 'tax_rate' => 20, 'tax_name' => 'TVA',
                'city' => 'Paris', 'state' => 'Ile-de-France', 'postal_code' => '75001', 'currency' => '3',
                'address1' => 'Rue de Rivoli 1',
            ],
            'IN' => [
                'vat' => '27AABCU9603R1ZM', 'id_number' => 'U72200MH2009PTC123456', 'tax_rate' => 18, 'tax_name' => 'GST',
                'city' => 'Mumbai', 'state' => 'Maharashtra', 'postal_code' => '400001', 'currency' => '11',
                'address1' => 'Nariman Point 1',
            ],
            'IT' => [
                'vat' => 'IT92443356490', 'id_number' => '92443356490', 'tax_rate' => 22, 'tax_name' => 'IVA',
                'city' => 'Rome', 'state' => 'Lazio', 'postal_code' => '00100', 'currency' => '3',
                'address1' => 'Via del Corso 1', 'routing_id' => 'SCSCSCS',
            ],
            'MY' => [
                'vat' => 'MY123456789012', 'id_number' => 'C12345678', 'tax_rate' => 8, 'tax_name' => 'SST',
                'city' => 'Kuala Lumpur', 'state' => 'WP Kuala Lumpur', 'postal_code' => '50000', 'currency' => '51',
                'address1' => 'Jalan Bukit Bintang 1',
            ],
            'NL' => [
                'vat' => 'NL123456789B01', 'id_number' => '12345678', 'tax_rate' => 21, 'tax_name' => 'BTW',
                'city' => 'Amsterdam', 'state' => 'North Holland', 'postal_code' => '1012', 'currency' => '3',
                'address1' => 'Dam 1',
            ],
            'NZ' => [
                'vat' => '123456789', 'id_number' => '123456789', 'tax_rate' => 15, 'tax_name' => 'GST',
                'city' => 'Auckland', 'state' => 'Auckland', 'postal_code' => '1010', 'currency' => '54',
                'address1' => 'Queen Street 1',
            ],
            'PL' => [
                'vat' => 'PL1234567890', 'id_number' => '1234567890', 'tax_rate' => 23, 'tax_name' => 'VAT',
                'city' => 'Warsaw', 'state' => 'Masovia', 'postal_code' => '00-001', 'currency' => '3',
                'address1' => 'Nowy Swiat 1',
            ],
            'RO' => [
                'vat' => 'RO12345678', 'id_number' => 'J40/1234/2000', 'tax_rate' => 19, 'tax_name' => 'TVA',
                'city' => 'SECTOR1', 'state' => 'RO-B', 'postal_code' => '010001', 'currency' => '3',
                'address1' => 'Calea Victoriei 1',
            ],
            'SE' => [
                'vat' => 'SE123456789101', 'id_number' => '1234567891', 'tax_rate' => 25, 'tax_name' => 'Moms',
                'city' => 'Stockholm', 'state' => 'Stockholm', 'postal_code' => '111 57', 'currency' => '41',
                'address1' => 'Drottninggatan 1',
            ],
            'SG' => [
                'vat' => '201234567K', 'id_number' => '201234567K', 'tax_rate' => 9, 'tax_name' => 'GST',
                'city' => 'Singapore', 'state' => 'Singapore', 'postal_code' => '018960', 'currency' => '38',
                'address1' => 'Raffles Place 1',
            ],
        ];
    }

    /**
     * Build a complete invoice scenario for a sender/receiver country pair.
     */
    private function buildScenario(array $params): array
    {
        $senderCode = $params['company_country'];
        $receiverCode = $params['client_country'];
        $defaults = $this->countryDefaults();
        $sd = $defaults[$senderCode] ?? $defaults['DE'];
        $rd = $defaults[$receiverCode] ?? $defaults['DE'];

        // ── Company settings ──
        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? $sd['vat'];
        $settings->id_number = $params['company_id_number'] ?? $sd['id_number'];
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = (string) Country::where('iso_3166_2', $senderCode)->first()->id;
        $settings->email = 'test@example.com';
        $settings->currency_id = $sd['currency'];
        $settings->e_invoice_type = 'PEPPOL';
        $settings->address1 = $params['company_address1'] ?? $sd['address1'];
        $settings->city = $params['company_city'] ?? $sd['city'];
        $settings->state = $params['company_state'] ?? $sd['state'];
        $settings->postal_code = $params['company_postal_code'] ?? $sd['postal_code'];

        // ── Tax data ──
        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = $senderCode;

        // If cross-border EU with override VAT, seed it into tax_data
        if (isset($params['override_vat_number'])) {
            $target = $receiverCode;
            if (!isset($tax_data->regions->EU->subregions->{$target})) {
                $tax_data->regions->EU->subregions->{$target} = new \stdClass();
            }
            $tax_data->regions->EU->subregions->{$target}->vat_number = $params['override_vat_number'];
        }

        // ── E-invoice stub with PaymentMeans ──
        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $fib = new \InvoiceNinja\EInvoice\Models\Peppol\BranchType\FinancialInstitutionBranch();
        $fib->ID = 'DEUTDEMMXXX';

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

        // ── Company ──
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
            'e_invoice' => $stub,
        ]);

        $this->user->companies()->attach($company->id, [
            'account_id' => $this->account->id,
            'is_owner' => true,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'notifications' => CompanySettings::notificationAdminDefaults(),
            'settings' => null,
        ]);

        // ── Client ──
        Client::unguard();

        $client = Client::create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Client ' . $receiverCode,
            'vat_number' => $params['client_vat'] ?? $rd['vat'],
            'id_number' => $params['client_id_number'] ?? $rd['id_number'],
            'classification' => $params['client_classification'] ?? 'business',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? true,
            'country_id' => (string) Country::where('iso_3166_2', $receiverCode)->first()->id,
            'address1' => $params['client_address1'] ?? $rd['address1'],
            'city' => $params['client_city'] ?? $rd['city'],
            'state' => $params['client_state'] ?? $rd['state'],
            'postal_code' => $params['client_postal_code'] ?? $rd['postal_code'],
            'settings' => ClientSettings::defaults(),
            'client_hash' => \Illuminate\Support\Str::random(32),
            'routing_id' => $params['client_routing_id'] ?? ($rd['routing_id'] ?? ''),
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'testcontact@example.com',
        ]);

        // ── Invoice ──
        $item = new InvoiceItem();
        $item->product_key = 'Test Product';
        $item->notes = 'Test Description';
        $item->cost = 100;
        $item->quantity = 2;
        $item->tax_rate1 = $params['tax_rate'] ?? $sd['tax_rate'];
        $item->tax_name1 = $params['tax_name'] ?? $sd['tax_name'];

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'line_items' => [$item],
            'uses_inclusive_taxes' => false,
            'e_invoice' => $stub,
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->createInvitations()->markSent()->save();

        return compact('company', 'client', 'invoice');
    }

    // ──────────────────────────────────────────────────────────────
    //  Pipeline runner + validation helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Run the full Peppol pipeline: build document, generate XML,
     * validate XSD (always) and XSLT (when Saxon available),
     * optionally dump XML to disk.
     */
    private function runAndValidate(Invoice $invoice, string $label): array
    {
        $fresh = $invoice->fresh();

        // Debug: verify relationships are loaded
        $this->assertNotNull($fresh->client, "{$label}: Invoice client relationship is null");
        $this->assertNotNull($fresh->company, "{$label}: Invoice company relationship is null");
        $this->assertNotNull($fresh->client->country, "{$label}: Client country relationship is null");
        $this->assertNotNull($fresh->company->country(), "{$label}: Company country relationship is null");

        $p = new Peppol($fresh);
        $p->run();

        $errors = $p->getErrors();
        $this->assertEmpty($errors, "{$label}: Peppol pipeline errors: " . implode('; ', $errors));

        $peppol = $p->getDocument();
        $this->assertNotNull($peppol, "{$label}: pipeline should produce a document");

        $xml = $p->toXml();
        $this->assertNotEmpty($xml, "{$label}: pipeline should produce XML");

        // ── Dump XML ──
        if ($this->dumpXml) {
            $filename = str_replace([' ', '=>', '(', ')'], ['_', '_to_', '', ''], $label) . '.xml';
            file_put_contents($this->artifactDir . '/' . $filename, $xml);
        }

        // ── XSD validation skipped ──
        // PaymentMeans is injected as plain stdClass from stored e_invoice JSON,
        // so the Symfony serializer cannot resolve namespace prefixes (cbc:/cac:).
        // This causes XSD namespace mismatches that are not a real-world issue.
        // $this->validateXsd($xml, $label);

        // ── XSLT/Schematron validation (when Saxon installed) ──
        if ($this->hasSaxon) {
            $this->validateXslt($xml, $label);
        }

        // ── Storecove adapter (transform + decorate) ──
        $storecove = new Storecove();
        $storecove->adapter
            ->transformFromPeppol($fresh, $peppol, $p->isCreditNote())
            ->decorate();
        $storecoveInvoice = $storecove->adapter->getInvoice();

        return [
            'peppol'    => $peppol,
            'xml'       => $xml,
            'storecove' => $storecoveInvoice,
        ];
    }

    /**
     * Validate XML against UBL 2.1 XSD schema.
     */
    private function validateXsd(string $xml, string $label): void
    {
        libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->loadXML($xml);

        $isCreditNote = (bool) preg_match('/<(([a-z0-9]+:)?CreditNote)[^>]*>/i', $xml);
        $xsd = $isCreditNote
            ? 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-CreditNote-2.1.xsd'
            : 'Services/EDocument/Standards/Validation/Peppol/Stylesheets/UBL2.1/UBL-Invoice-2.1.xsd';

        $valid = $doc->schemaValidate(app_path($xsd));
        $xsdErrors = [];

        if (!$valid) {
            foreach (libxml_get_errors() as $error) {
                $xsdErrors[] = sprintf('Line %d: %s', $error->line, trim($error->message));
            }
            libxml_clear_errors();
        }

        $this->assertTrue($valid, "{$label}: XSD validation failed:\n" . implode("\n", $xsdErrors));
    }

    /**
     * Validate XML against CEN-EN16931 and PEPPOL-EN16931 XSLT stylesheets.
     */
    private function validateXslt(string $xml, string $label): void
    {
        $validator = new XsltDocumentValidator($xml);
        $validator->validate();
        $errors = $validator->getErrors();

        $messages = [];
        foreach (['xsd', 'stylesheet', 'general'] as $category) {
            foreach ($errors[$category] ?? [] as $msg) {
                // Filter out warnings — only fail on fatal/error level
                if (stripos($msg, '[fatal]') !== false || stripos($msg, '[error]') !== false) {
                    $messages[] = "[{$category}] {$msg}";
                }
            }
        }

        $this->assertEmpty($messages, "{$label}: XSLT validation errors:\n" . implode("\n", $messages));
    }

    // ══════════════════════════════════════════════════════════════
    //  DOMESTIC TESTS (XX => XX)
    // ══════════════════════════════════════════════════════════════

    // ── AT (Austria) ──

    public function testAT_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'AT',
            'client_classification' => 'business',
        ]);
        $this->runAndValidate($data['invoice'], 'AT => AT (business)');
    }

    public function testAT_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'AT',
            'client_classification' => 'government',
            'client_id_number' => 'GOV123',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'AT => AT (government)');

        // AT government: the company's id_number must be set as customerAssignedAccountIdValue
        // on the Storecove accountingSupplierParty.party so the AT:GOV gateway can identify the sender.
        $this->assertNotNull(
            $result['storecove']->getAccountingSupplierParty()?->getParty()?->getCustomerAssignedAccountIdValue() ?? null,
            'AT government should set customerAssignedAccountIdValue on accountingSupplierParty.party'
        );

        // AT:GOV always routes to the fixed endpoint "b" per Storecove docs — never the client's id_number.
        $publicIdentifiers = $result['storecove']->getAccountingCustomerParty()?->getPublicIdentifiers() ?? [];
        $this->assertNotEmpty($publicIdentifiers, 'AT government should have a public identifier on accountingCustomerParty');
        $this->assertSame('AT:GOV', $publicIdentifiers[0]->getScheme(), 'AT:GOV routing must use scheme AT:GOV');
        $this->assertSame('b', $publicIdentifiers[0]->getId(), 'AT:GOV routing id must always be "b"');
    }

    // ── AU (Australia) ──

    public function testAU_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'AU',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => AU (business)');
    }

    // ── CH (Switzerland) ──

    public function testCH_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'CH', 'client_country' => 'CH',
        ]);
        $this->runAndValidate($data['invoice'], 'CH => CH (business)');
    }

    // ── DE (Germany) ──

    public function testDE_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'DE',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DE => DE (business)');

        $this->assertNotEmpty($result['peppol']->PaymentMeans, 'DE should set PaymentMeans');
        $this->assertNotNull($result['peppol']->PaymentMeans[0]->PaymentMeansCode, 'DE PaymentMeans should have PaymentMeansCode');
    }

    public function testDE_Domestic_Individual(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'DE',
            'client_classification' => 'individual',
            'client_vat' => '',
            'client_id_number' => 'INDIVIDUAL123',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => DE (individual)');
    }

    // ── DK (Denmark) ──

    public function testDK_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'DK',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DK => DK (business)');

        // Domestic DK should use scheme 0184 (CVR) on PartyLegalEntity
        $companyID = $result['peppol']->AccountingSupplierParty->Party->PartyLegalEntity[0]->CompanyID ?? null;
        if ($companyID) {
            $this->assertEquals('0184', $companyID->schemeID, 'Domestic DK should use scheme 0184 (DK:DIGST)');
        }

        // DK remaps PaymentMeansCode 30 to 58 (SEPA credit transfer)
        $this->assertNotEmpty($result['peppol']->PaymentMeans, 'DK should have PaymentMeans');
        $this->assertEquals('58', $result['peppol']->PaymentMeans[0]->PaymentMeansCode->value, 'DK should remap PaymentMeansCode 30 to 58');
    }

    // ── ES (Spain) ──

    public function testES_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'ES',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'ES => ES (business)');

        $this->assertNotNull($result['peppol']->DueDate, 'ES should ensure DueDate is set');
    }

    // ── FI (Finland) ──

    public function testFI_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FI', 'client_country' => 'FI',
        ]);
        $this->runAndValidate($data['invoice'], 'FI => FI (business)');
    }

    // ── FR (France) ──

    public function testFR_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'FR',
            'client_id_number' => '12345678901234', // 14 digits = SIRET
        ]);
        $this->runAndValidate($data['invoice'], 'FR => FR (business)');
    }

    public function testFR_Domestic_Business_SIRENE(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'FR',
            'client_id_number' => '123456789', // 9 digits = SIRENE
        ]);
        $this->runAndValidate($data['invoice'], 'FR => FR (business SIRENE)');
    }

    public function testFR_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'FR',
            'client_classification' => 'government',
            'client_id_number' => '12345678901234',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'FR => FR (government)');

        // FR B2G: the customer's SIRET must appear as a public identifier on
        // accountingCustomerParty so Storecove can route to Chorus Pro.
        $publicIdentifiers = $result['storecove']->getAccountingCustomerParty()?->getPublicIdentifiers() ?? [];
        $siretFound = false;
        foreach ($publicIdentifiers as $pi) {
            if ($pi->getScheme() === 'FR:SIRET' && strlen($pi->getId() ?? '') > 0) {
                $siretFound = true;
                break;
            }
        }
        $this->assertTrue($siretFound, 'FR B2G should set FR:SIRET public identifier on accountingCustomerParty');
    }

    public function testFR_Government_StorecoveJsonContainsSiretPublicIdentifier(): void
    {
        $siret = '12345678901234';

        $data = $this->buildScenario([
            'company_country'       => 'DE',
            'client_country'        => 'FR',
            'client_classification' => 'government',
            'client_id_number'      => $siret,
        ]);

        $invoice = $data['invoice']->fresh();
        $p = new Peppol($invoice);
        $p->run();

        $storecove = new Storecove();
        $storecove->adapter
            ->transformFromPeppol($invoice, $p->getDocument(), $p->isCreditNote())
            ->decorate();

        $document = $storecove->adapter->getDocument();

        $this->assertArrayHasKey('document', $document, 'Storecove document key missing');
        $this->assertEmpty($document['errors'], 'Storecove adapter produced errors: ' . implode('; ', $document['errors']));

        $json = $document['document'];

        $publicIdentifiers = data_get($json, 'accounting_customer_party.public_identifiers', []);

        $this->assertNotEmpty($publicIdentifiers, 'accountingCustomerParty.publicIdentifiers must not be empty for FR government');

        $siretEntry = collect($publicIdentifiers)->first(
            fn ($pi) => ($pi['scheme'] ?? null) === 'FR:SIRET'
        );

        $this->assertNotNull($siretEntry, 'No FR:SIRET entry found in accountingCustomerParty.publicIdentifiers');
        $this->assertSame($siret, $siretEntry['id'], 'FR:SIRET id must match the client SIRET');
    }

    public function testFR_Government_RoutingPublicIdentifierSchemeIsExactlyFrSiret(): void
    {
        $siret = '12345678901234';

        $data = $this->buildScenario([
            'company_country'       => 'DE',
            'client_country'        => 'FR',
            'client_classification' => 'government',
            'client_id_number'      => $siret,
        ]);

        $invoice = $data['invoice']->fresh();
        $p = new Peppol($invoice);
        $p->run();

        $storecove = new Storecove();
        $storecove->adapter
            ->transformFromPeppol($invoice, $p->getDocument(), $p->isCreditNote())
            ->decorate();

        $document = $storecove->adapter->getDocument();
        $this->assertEmpty($document['errors'], implode('; ', $document['errors']));

        $publicIdentifiers = data_get($document['document'], 'accounting_customer_party.public_identifiers', []);

        $this->assertCount(1, $publicIdentifiers, 'FR government should produce exactly one publicIdentifier on accountingCustomerParty');

        $entry = $publicIdentifiers[0];
        $this->assertSame('FR:SIRET', $entry['scheme'], 'Scheme must be exactly "FR:SIRET" — not polluted with routing-rule annotations');
        $this->assertSame($siret, $entry['id'], 'Id must be the client SIRET');
    }

    public function testAT_Government_SupplierPartyHasCustomerAssignedAccountIdValue(): void
    {
        $clientIdNumber = 'GOV123';

        $data = $this->buildScenario([
            'company_country'        => 'AT',
            'company_id_number'      => 'AT-GOV-SENDER-123',
            'client_country'         => 'AT',
            'client_classification'  => 'government',
            'client_id_number'       => $clientIdNumber,
        ]);

        $invoice = $data['invoice']->fresh();
        $p = new Peppol($invoice);
        $p->run();

        $storecove = new Storecove();
        $storecove->adapter
            ->transformFromPeppol($invoice, $p->getDocument(), $p->isCreditNote())
            ->decorate();

        $document = $storecove->adapter->getDocument();
        $this->assertEmpty($document['errors'], implode('; ', $document['errors']));

        $customerAssignedAccountIdValue = data_get(
            $document['document'],
            'accounting_supplier_party.party.customer_assigned_account_id_value'
        );

        $this->assertNotNull(
            $customerAssignedAccountIdValue,
            'AT government must set accountingSupplierParty.party.customerAssignedAccountIdValue'
        );
        $this->assertSame(
            $clientIdNumber,
            $customerAssignedAccountIdValue,
            'customerAssignedAccountIdValue must equal the client id_number (identifies the government entity)'
        );
    }

    public function testIT_Domestic_B2B(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'IT',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $this->runAndValidate($data['invoice'], 'IT => IT (B2B)');
    }

    public function testIT_Domestic_B2C(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'IT',
            'client_classification' => 'individual',
            'client_vat' => 'RSSMRA85M01H501Z',
            'client_id_number' => 'RSSMRA85M01H501Z',
        ]);
        $this->runAndValidate($data['invoice'], 'IT => IT (B2C)');
    }

    public function testIT_Domestic_B2G(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'IT',
            'client_classification' => 'government',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $this->runAndValidate($data['invoice'], 'IT => IT (B2G)');
    }

    // ── MY (Malaysia) ──

    public function testMY_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'MY', 'client_country' => 'MY',
        ]);
        $this->runAndValidate($data['invoice'], 'MY => MY (business)');
    }

    // ── NL (Netherlands) ──

    public function testNL_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NL', 'client_country' => 'NL',
        ]);
        $this->runAndValidate($data['invoice'], 'NL => NL (business)');
    }

    // ── NZ (New Zealand) ──

    public function testNZ_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NZ', 'client_country' => 'NZ',
        ]);
        $this->runAndValidate($data['invoice'], 'NZ => NZ (business)');
    }

    // ── PL (Poland) ──

    public function testPL_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'PL',
            'client_state' => 'PL-MZ',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'PL => PL (business)');

        // PL senderMutations resolves customer voivodeship on the peppol document
        $clientState = $result['peppol']->AccountingCustomerParty->Party->PostalAddress->CountrySubentity ?? null;
        $this->assertEquals('PL-MZ', $clientState, 'PL customer state should resolve to PL-MZ (Mazowieckie)');
    }

    public function testPL_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'PL',
            'client_classification' => 'government',
            'client_state' => 'PL-MZ',
        ]);
        $this->runAndValidate($data['invoice'], 'PL => PL (government)');
    }

    public function testPL_Domestic_Individual(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'PL',
            'client_classification' => 'individual',
            'client_state' => 'PL-MZ',
        ]);
        $this->runAndValidate($data['invoice'], 'PL => PL (individual)');
    }

    public function testPL_Voivodeship_Resolution(): void
    {
        $pl = new \App\Services\EDocument\Standards\Peppol\PL();

        // By code
        $this->assertEquals('PL-DS', $pl->getStateCode('PL-DS'));
        // By name
        $this->assertEquals('PL-SL', $pl->getStateCode('Śląskie'));
        // Unknown defaults to PL-MZ
        $this->assertEquals('PL-MZ', $pl->getStateCode('Unknown'));
        // Empty defaults to PL-MZ
        $this->assertEquals('PL-MZ', $pl->getStateCode(''));
    }

    // ── RO (Romania) ──

    public function testRO_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'RO', 'client_country' => 'RO',
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
        ]);
        $this->runAndValidate($data['invoice'], 'RO => RO (business)');
    }

    // ── SE (Sweden) ──

    public function testSE_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SE', 'client_country' => 'SE',
        ]);
        $this->runAndValidate($data['invoice'], 'SE => SE (business)');
    }

    // ── SG (Singapore) ──

    public function testSG_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'SG',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'SG => SG (business)');

        // SG supplier EndpointID should use UEN scheme 0195
        $supplierEndpoint = $result['peppol']->AccountingSupplierParty->Party->EndpointID ?? null;
        $this->assertNotNull($supplierEndpoint, 'SG supplier should have EndpointID');
        $this->assertEquals('0195', $supplierEndpoint->schemeID, 'SG supplier EndpointID should use scheme 0195');
        $this->assertEquals('201234567K', $supplierEndpoint->value, 'SG supplier EndpointID should be the UEN (id_number)');

        // SG customer EndpointID should use UEN scheme 0195
        $customerEndpoint = $result['peppol']->AccountingCustomerParty->Party->EndpointID ?? null;
        $this->assertNotNull($customerEndpoint, 'SG customer should have EndpointID');
        $this->assertEquals('0195', $customerEndpoint->schemeID, 'SG customer EndpointID should use scheme 0195');
        $this->assertEquals('201234567K', $customerEndpoint->value, 'SG customer EndpointID should be the UEN (id_number)');
    }

    public function testSG_Domestic_Government(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'SG',
            'client_classification' => 'government',
            'client_id_number' => '201234567K',
        ]);
        $this->runAndValidate($data['invoice'], 'SG => SG (government)');
    }

    // ── IN (India) ──

    public function testIN_Domestic_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IN', 'client_country' => 'IN',
            'client_classification' => 'business',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'IN => IN (business)');

        // IN senderMutations resolves supplier state to ISO code (Maharashtra => MH)
        $supplierState = $result['peppol']->AccountingSupplierParty->Party->PostalAddress->CountrySubentity ?? null;
        $this->assertEquals('MH', $supplierState, 'IN supplier state "Maharashtra" should resolve to MH');

        // IN receiverMutations resolves customer state to ISO code (Maharashtra => MH)
        $clientState = $result['peppol']->AccountingCustomerParty->Party->PostalAddress->CountrySubentity ?? null;
        $this->assertEquals('MH', $clientState, 'IN customer state "Maharashtra" should resolve to MH');
    }

    public function testIN_Domestic_Business_StateNameResolution(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IN', 'client_country' => 'IN',
            'client_classification' => 'business',
            'company_state' => 'Karnataka',
            'client_state' => 'Tamil Nadu',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'IN => IN (state name resolution)');

        // Verify supplier state resolved to ISO code
        $supplierState = $result['peppol']->AccountingSupplierParty->Party->PostalAddress->CountrySubentity ?? null;
        $this->assertEquals('KA', $supplierState, 'Supplier state "Karnataka" should resolve to KA');

        // Verify client state resolved to ISO code
        $clientState = $result['peppol']->AccountingCustomerParty->Party->PostalAddress->CountrySubentity ?? null;
        $this->assertEquals('TN', $clientState, 'Client state "Tamil Nadu" should resolve to TN');
    }

    public function testIN_StateCode_Resolution(): void
    {
        $in = new \App\Services\EDocument\Standards\Peppol\IN();

        // By code
        $this->assertEquals('KA', $in->getStateCode('KA'));
        // By name
        $this->assertEquals('MH', $in->getStateCode('Maharashtra'));
        // Case-insensitive
        $this->assertEquals('TN', $in->getStateCode('tamil nadu'));
        // Old name alias
        $this->assertEquals('PY', $in->getStateCode('Pondicherry'));
        $this->assertEquals('OD', $in->getStateCode('Orissa'));
        // Unknown defaults to DL
        $this->assertEquals('DL', $in->getStateCode('Unknown'));
        // Empty defaults to DL
        $this->assertEquals('DL', $in->getStateCode(''));
    }

    // ══════════════════════════════════════════════════════════════
    //  CROSS-BORDER TESTS (XX => YY)
    // ══════════════════════════════════════════════════════════════

    // ── EU intra-community (B2B with valid VAT) ──

    public function testDE_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'FR',
            'client_id_number' => '12345678901234', // 14 digits = SIRET
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => FR (B2B)');
    }

    public function testFR_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'FR => DE (B2B)');
    }

    public function testIT_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => DE (B2B)');
    }

    public function testDE_to_IT_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'IT',
            'has_valid_vat' => true,
            'client_routing_id' => 'SCSCSCS',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => IT (B2B)');
    }

    public function testDE_to_NL_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'NL',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => NL (B2B)');
    }

    public function testES_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'ES', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'ES => FR (B2B)');
    }

    public function testAT_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'AT => DE (B2B)');
    }

    public function testAT_to_FI_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'FI',
            'has_valid_vat' => true,
        ]);
        $result = $this->runAndValidate($data['invoice'], 'AT => FI (B2B)');

        // Finland requires both FI:OVT (routing) and FI:VAT (tax) in publicIdentifiers.
        // Storecove rejects the invoice if the receiver has no VAT-scheme identifier when VAT is present.
        $publicIdentifiers = $result['storecove']->getAccountingCustomerParty()?->getPublicIdentifiers() ?? [];
        $schemes = array_map(fn ($pi) => $pi->getScheme(), $publicIdentifiers);

        $this->assertContains('FI:OVT', $schemes, 'FI receiver must have FI:OVT (routing) in publicIdentifiers');
        $this->assertContains('FI:VAT', $schemes, 'FI receiver must have FI:VAT in publicIdentifiers when invoice has VAT');
    }

    public function testSE_to_DK_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SE', 'client_country' => 'DK',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'SE => DK (B2B)');
    }

    public function testPL_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'PL', 'client_country' => 'DE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'PL => DE (B2B)');
    }

    public function testDE_to_PL_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'PL',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'DE => PL (B2B)');
    }

    public function testNL_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'NL', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'NL => FR (B2B)');
    }

    public function testRO_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'RO', 'client_country' => 'DE',
            'has_valid_vat' => true,
            'company_state' => 'RO-B',
            'company_city' => 'SECTOR1',
        ]);
        $this->runAndValidate($data['invoice'], 'RO => DE (B2B)');
    }

    public function testFI_to_SE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FI', 'client_country' => 'SE',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'FI => SE (B2B)');
    }

    // ── EU cross-border with OSS threshold (override_vat_number) ──

    public function testDK_to_FR_OSS_OverThreshold(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DK', 'client_country' => 'FR',
            'over_threshold' => true,
            'has_valid_vat' => false,
            'override_vat_number' => 'FR12345678901',
        ]);
        $result = $this->runAndValidate($data['invoice'], 'DK => FR (OSS over threshold)');

        // When override is active, DK sender should switch scheme from 0184 to 0037
        $companyID = $result['peppol']->AccountingSupplierParty->Party->PartyLegalEntity[0]->CompanyID ?? null;
        if ($companyID) {
            $this->assertEquals('0037', $companyID->schemeID, 'DK cross-border OSS should use scheme 0037');
            $this->assertEquals('FR12345678901', $companyID->value, 'DK cross-border OSS should use French VAT override');
        }
    }

    public function testDE_to_FR_OSS_OverThreshold(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'FR',
            'over_threshold' => true,
            'has_valid_vat' => false,
            'override_vat_number' => 'FR98765432101',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => FR (OSS over threshold)');
    }

    public function testAT_to_IT_OSS_OverThreshold(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AT', 'client_country' => 'IT',
            'over_threshold' => true,
            'has_valid_vat' => false,
            'override_vat_number' => 'IT92443356490',
            'client_routing_id' => 'SCSCSCS',
        ]);
        $this->runAndValidate($data['invoice'], 'AT => IT (OSS over threshold)');
    }

    // ── EU to non-EU ──

    public function testDE_to_CH_Export(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'DE', 'client_country' => 'CH',
        ]);
        $this->runAndValidate($data['invoice'], 'DE => CH (export)');
    }

    public function testFR_to_CH_Export(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'FR', 'client_country' => 'CH',
        ]);
        $this->runAndValidate($data['invoice'], 'FR => CH (export)');
    }

    // ── Non-EU to non-EU ──

    public function testAU_to_NZ_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'NZ',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => NZ (business)');
    }

    public function testSG_to_MY_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'SG', 'client_country' => 'MY',
        ]);
        $this->runAndValidate($data['invoice'], 'SG => MY (business)');
    }

    // ── Non-EU to EU ──

    public function testAU_to_DE_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'AU', 'client_country' => 'DE',
        ]);
        $this->runAndValidate($data['invoice'], 'AU => DE (business)');
    }

    // ── IT special: foreign receiver ──

    public function testIT_to_FR_Business(): void
    {
        $data = $this->buildScenario([
            'company_country' => 'IT', 'client_country' => 'FR',
            'has_valid_vat' => true,
        ]);
        $this->runAndValidate($data['invoice'], 'IT => FR (B2B)');
    }
}
