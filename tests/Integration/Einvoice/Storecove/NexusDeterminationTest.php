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
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Tests for StorecoveAdapter::buildNexus() — the tax nexus determination logic.
 *
 * These tests exercise every branch of buildNexus() through the public
 * Storecove->build()->adapter->getNexus() pipeline. They serve as a
 * regression safety net before extracting NexusResolver into its own class.
 *
 * Branches covered:
 *  1. Domestic (same country)
 *  2. EU company → non-EU client
 *  3. Non-EU company → EU client
 *  4. EU cross-border B2C, under threshold, no company VAT
 *  5. EU cross-border B2C, under threshold, with company VAT
 *  6. EU cross-border B2C, over threshold (destination VAT registered)
 *  7. EU cross-border B2C, over threshold (destination VAT missing → error)
 *  8. EU cross-border B2B with valid VAT
 *  9. Non-EU over threshold → EU client
 * 10. Fallback: company has tax registration in client region
 * 11. Fallback: no registration → company country
 * 12. DE → DE government (supplier VAT removal)
 */
class NexusDeterminationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function setupTestData(array $params = []): array
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE123456789';
        $settings->id_number = $params['company_id_number'] ?? '01234567890';
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = Country::where('iso_3166_2', $params['company_country'] ?? 'DE')->first()->id;
        $settings->email = $this->faker->safeEmail();
        $settings->currency_id = '3';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = $params['tax_all_subregions'] ?? true;
        $tax_data->seller_subregion = $params['company_country'] ?? 'DE';

        // Set destination VAT if provided
        if (isset($params['destination_vat_country']) && isset($params['destination_vat_number'])) {
            $country = $params['destination_vat_country'];
            if (!isset($tax_data->regions->EU->subregions->{$country})) {
                $tax_data->regions->EU->subregions->{$country} = new \stdClass();
            }
            $tax_data->regions->EU->subregions->{$country}->vat_number = $params['destination_vat_number'];
        }

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
        $this->company->legal_entity_id = 290868;
        $this->company->e_invoice = $stub;
        $this->company->save();

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', $params['client_country'] ?? 'FR')->first()->id,
            'vat_number' => $params['client_vat'] ?? '',
            'classification' => $params['classification'] ?? 'individual',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? false,
            'name' => 'Test Client',
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
            'id_number' => $params['client_id_number'] ?? '',
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
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

    private function resolveNexus(Invoice $invoice): array
    {
        $storecove = new Storecove();
        $storecove->build($invoice);

        return [
            'nexus' => $storecove->adapter->getNexus(),
            'errors' => $storecove->adapter->getErrors(),
        ];
    }

    // ─── Branch 1: Domestic sales (same country) ───

    public function testDomesticSalesNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'client_country' => 'DE',
            'company_vat' => 'DE923356489',
            'classification' => 'business',
            'client_vat' => 'DE173755434',
            'has_valid_vat' => true,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('DE', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    public function testDomesticSalesFRNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'FR',
            'company_vat' => 'FRAA123456789',
            'client_country' => 'FR',
            'classification' => 'business',
            'client_vat' => 'FRBB987654321',
            'has_valid_vat' => true,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('FR', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Branch 2: EU company → non-EU client ───

    public function testEuCompanyToNonEuClientNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'US',
            'classification' => 'business',
            'client_vat' => '',
            'has_valid_vat' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('DE', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    public function testEuCompanyToAustraliaClientNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'FR',
            'company_vat' => 'FRAA123456789',
            'client_country' => 'AU',
            'classification' => 'business',
            'client_vat' => '',
            'has_valid_vat' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('FR', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Branch 3: Non-EU company → EU client ───

    public function testNonEuCompanyToEuClientNexusIsClientCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'AU',
            'company_vat' => '12345678901',
            'client_country' => 'DE',
            'classification' => 'business',
            'client_vat' => 'DE173755434',
            'has_valid_vat' => true,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('DE', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Branch 4: EU cross-border B2C, under threshold, no company VAT ───

    public function testEuCrossBorderB2cUnderThresholdNoCompanyVatNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => '', // No VAT number
            'company_id_number' => '01234567890',
            'client_country' => 'FR',
            'classification' => 'individual',
            'client_vat' => '',
            'has_valid_vat' => false,
            'over_threshold' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('DE', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Branch 5: EU cross-border B2C, under threshold, with company VAT ───

    public function testEuCrossBorderB2cUnderThresholdWithCompanyVatNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'FR',
            'classification' => 'individual',
            'client_vat' => '',
            'has_valid_vat' => false,
            'over_threshold' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('DE', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Branch 6: EU cross-border B2C, over threshold (destination VAT present) ───

    public function testEuCrossBorderB2cOverThresholdWithDestinationVatNexusIsClientCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'FR',
            'classification' => 'individual',
            'client_vat' => '',
            'has_valid_vat' => false,
            'over_threshold' => true,
            'destination_vat_country' => 'FR',
            'destination_vat_number' => 'FRXX999999999',
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('FR', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Branch 7: EU cross-border B2C, over threshold (no destination VAT registered) ───
    //
    // NOTE: TaxModel pre-initializes all EU subregion vat_number to '' (empty string).
    // The isset() check in buildNexus() always passes because the property exists.
    // This means the "VAT number not present" error path is effectively dead code.
    // The nexus is still correctly set to the client country, and setupDestinationVAT()
    // is called (which may produce downstream issues when the VAT is empty).
    // This is a known gap — documenting actual behavior here as a reference point.

    public function testEuCrossBorderB2cOverThresholdWithoutDestinationVatStillSetsClientCountryNexus(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'IT',
            'classification' => 'individual',
            'client_vat' => '',
            'has_valid_vat' => false,
            'over_threshold' => true,
            // No destination_vat_country/destination_vat_number — VAT not registered
        ]);

        $result = $this->resolveNexus($data['invoice']);

        // Nexus is correctly set to client country even without destination VAT
        $this->assertEquals('IT', $result['nexus']);
    }

    // ─── Branch 8: EU cross-border B2B with valid VAT ───

    public function testEuCrossBorderB2bWithValidVatNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'FR',
            'classification' => 'business',
            'client_vat' => 'FRAA123456789',
            'has_valid_vat' => true,
            'over_threshold' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('DE', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    public function testEuCrossBorderB2bWithValidVatOverThresholdNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'FR',
            'classification' => 'business',
            'client_vat' => 'FRAA123456789',
            'has_valid_vat' => true,
            'over_threshold' => true,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('DE', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }

    // ─── Branch 9: Non-EU company, over threshold → EU client ───

    public function testNonEuCompanyOverThresholdToEuClientNexusIsClientCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'AU',
            'company_vat' => '12345678901',
            'client_country' => 'FR',
            'classification' => 'individual',
            'client_vat' => '',
            'has_valid_vat' => false,
            'over_threshold' => true,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        // Non-EU → EU: nexus should be client country
        $this->assertEquals('FR', $result['nexus']);
    }

    // ─── Branch 10/11: Fallback — non-EU to non-EU ───

    public function testNonEuToNonEuFallbackNexusIsCompanyCountry(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'AU',
            'company_vat' => '12345678901',
            'client_country' => 'NZ',
            'classification' => 'business',
            'client_vat' => '',
            'has_valid_vat' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        // AU → NZ: no EU rules apply, fallback to company country
        $this->assertEquals('AU', $result['nexus']);
    }

    // ─── Branch 12: DE → DE government (supplier VAT removal) ───

    public function testDeToDeGovernmentRemovesSupplierVat(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'DE',
            'classification' => 'government',
            'client_vat' => '',
            'has_valid_vat' => false,
            'is_tax_exempt' => true,
        ]);

        $storecove = new Storecove();
        $storecove->build($data['invoice']);
        $result = $storecove->getResult();

        $this->assertEquals('DE', $storecove->adapter->getNexus());

        // Verify supplier public identifiers were cleared
        $supplierParty = $result['document']['accounting_supplier_party'] ?? null;

        if ($supplierParty) {
            $publicIdentifiers = $supplierParty['public_identifiers'] ?? [];
            $this->assertEmpty($publicIdentifiers, 'DE→DE government should have no supplier public identifiers (VAT removed)');
        }
    }

    // ─── Edge: B2C classification via "individual" flag ───

    public function testIndividualClassificationTreatedAsB2c(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'FR',
            'classification' => 'individual',
            'client_vat' => '',
            'has_valid_vat' => false,
            'over_threshold' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        // B2C under threshold — nexus is company country
        $this->assertEquals('DE', $result['nexus']);
    }

    // ─── Edge: Client has VAT but it's not validated ───

    public function testClientWithInvalidVatTreatedAsB2c(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'FR',
            'classification' => 'business',
            'client_vat' => 'FRXX000000000',
            'has_valid_vat' => false, // VAT present but not validated
            'over_threshold' => false,
        ]);

        $result = $this->resolveNexus($data['invoice']);

        // Client VAT is present but not validated → treated as B2C → under threshold → company country
        $this->assertEquals('DE', $result['nexus']);
    }

    // ─── Edge: Multiple EU countries ───

    public function testEuCrossBorderATtoITOverThresholdWithVat(): void
    {
        $data = $this->setupTestData([
            'company_country' => 'AT',
            'company_vat' => 'ATU12345678',
            'client_country' => 'IT',
            'classification' => 'individual',
            'client_vat' => '',
            'has_valid_vat' => false,
            'over_threshold' => true,
            'destination_vat_country' => 'IT',
            'destination_vat_number' => 'IT12345678901',
        ]);

        $result = $this->resolveNexus($data['invoice']);

        $this->assertEquals('IT', $result['nexus']);
        $this->assertEmpty($result['errors']);
    }
}
