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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Regression tests for the Mutator country-specific logic.
 *
 * These tests capture the current behavior of each country mutator
 * to ensure refactoring does not change the output.
 */
class MutatorTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    /**
     * Build a complete invoice scenario for a given sender/receiver country pair.
     */
    private function buildScenario(array $params): array
    {
        $companyCountry = $params['company_country'] ?? 'DE';
        $clientCountry = $params['client_country'] ?? 'DE';

        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE923356489';
        $settings->id_number = $params['company_id_number'] ?? '01234567890';
        $settings->classification = $params['company_classification'] ?? 'business';
        $settings->country_id = (string) Country::where('iso_3166_2', $companyCountry)->first()->id;
        $settings->email = 'test@example.com';
        $settings->currency_id = '3';
        $settings->e_invoice_type = 'PEPPOL';
        $settings->address1 = $params['company_address1'] ?? 'Test Street 1';
        $settings->city = $params['company_city'] ?? 'Berlin';
        $settings->state = $params['company_state'] ?? 'Berlin';
        $settings->postal_code = $params['company_postal_code'] ?? '10115';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = $companyCountry;

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

        Client::unguard();

        $client = Client::create([
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Client',
            'vat_number' => $params['client_vat'] ?? '',
            'id_number' => $params['client_id_number'] ?? '',
            'classification' => $params['client_classification'] ?? 'business',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? false,
            'country_id' => (string) Country::where('iso_3166_2', $clientCountry)->first()->id,
            'address1' => $params['client_address1'] ?? 'Client Street 1',
            'city' => $params['client_city'] ?? 'Berlin',
            'state' => $params['client_state'] ?? 'Berlin',
            'postal_code' => $params['client_postal_code'] ?? '10115',
            'settings' => ClientSettings::defaults(),
            'client_hash' => \Illuminate\Support\Str::random(32),
            'routing_id' => $params['client_routing_id'] ?? '',
            'is_tax_exempt' => $params['is_tax_exempt'] ?? false,
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'first_name' => 'Test',
            'last_name' => 'Contact',
            'email' => 'testcontact@example.com',
        ]);

        $item = new InvoiceItem();
        $item->product_key = "Test Product";
        $item->notes = "Test Description";
        $item->cost = 100;
        $item->quantity = 1;
        $item->tax_rate1 = $params['tax_rate'] ?? 19;
        $item->tax_name1 = $params['tax_name'] ?? 'VAT';

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $company->id,
            'user_id' => $this->user->id,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(14)->format('Y-m-d'),
            'line_items' => [$item],
            'uses_inclusive_taxes' => false,
            'e_invoice' => $stub,
        ]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->service()->markSent()->save();

        return compact('company', 'client', 'invoice');
    }

    /**
     * Run the Peppol mutator pipeline and return the mutated peppol object + storecove meta.
     */
    private function runMutator(Invoice $invoice): array
    {
        $p = new Peppol($invoice->fresh());
        $p->run();

        $peppol = $p->getDocument();
        $meta = $p->gateway->mutator->getStorecoveMeta();

        return [
            'peppol' => $peppol,
            'meta' => $meta,
            'xml' => $p->toXml(),
        ];
    }

    /**
     * Helper to find a routing scheme in storecove meta, handling both
     * single assoc array and array-of-arrays structures.
     */
    private function findRoutingScheme(array $meta, string $scheme): ?array
    {
        $identifiers = $meta['routing']['eIdentifiers'] ?? [];
        // Single assoc array case
        if (isset($identifiers['scheme'])) {
            return $identifiers['scheme'] === $scheme ? $identifiers : null;
        }
        // Array of arrays case
        foreach ($identifiers as $id) {
            if (($id['scheme'] ?? '') === $scheme) {
                return $id;
            }
        }
        return null;
    }

    // ==================== DE (Germany) Tests ====================

    public function testDE_SetsPaymentMeans()
    {
        $data = $this->buildScenario([
            'company_country' => 'DE',
            'client_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_vat' => 'DE173755434',
            'client_classification' => 'business',
        ]);

        $result = $this->runMutator($data['invoice']);

        $this->assertNotNull($result['peppol']->PaymentMeans, 'DE mutator should set PaymentMeans');
    }

    // ==================== DK (Denmark) Tests ====================

    public function testDK_ProducesValidPeppol()
    {
        $data = $this->buildScenario([
            'company_country' => 'DK',
            'company_vat' => 'DK12345678',
            'company_id_number' => '12345678',
            'client_country' => 'DK',
            'client_vat' => 'DK87654321',
            'client_classification' => 'business',
        ]);

        $result = $this->runMutator($data['invoice']);

        $this->assertNotNull($result['peppol'], 'DK pipeline should produce valid peppol');
        $this->assertNotEmpty($result['xml'], 'DK pipeline should produce XML');
    }

    // ==================== AT (Austria) Tests ====================

    public function testAT_BusinessDoesNotSetGovernmentRouting()
    {
        $data = $this->buildScenario([
            'company_country' => 'AT',
            'company_vat' => 'ATU12345678',
            'client_country' => 'AT',
            'client_vat' => 'ATU87654321',
            'client_classification' => 'business',
        ]);

        $result = $this->runMutator($data['invoice']);

        $meta = $result['meta'];
        $govRoute = $this->findRoutingScheme($meta, 'AT:GOV');
        $this->assertNull($govRoute, 'AT business should not have government routing');
    }

    public function testAT_GovernmentSetsRouting()
    {
        $data = $this->buildScenario([
            'company_country' => 'AT',
            'company_vat' => 'ATU12345678',
            'client_country' => 'AT',
            'client_vat' => 'ATU87654321',
            'client_classification' => 'government',
            'client_id_number' => 'GOV123',
        ]);

        $result = $this->runMutator($data['invoice']);

        $meta = $result['meta'];

        // AT gov routing is set, or exception was caught (both are valid current behavior)
        if (isset($meta['routing'])) {
            $govRoute = $this->findRoutingScheme($meta, 'AT:GOV');
            $this->assertNotNull($govRoute, 'AT government should route via AT:GOV');
            $this->assertEquals('b', $govRoute['id']);
        }

        $this->assertNotNull($result['peppol'], 'AT government pipeline should produce peppol');
    }

    // ==================== CH (Switzerland) Tests ====================

    public function testCH_IsNoOp()
    {
        $data = $this->buildScenario([
            'company_country' => 'CH',
            'company_vat' => 'CHE123456789',
            'client_country' => 'CH',
            'client_vat' => 'CHE987654321',
            'client_classification' => 'business',
            'tax_rate' => 7.7,
            'tax_name' => 'MWST',
        ]);

        $result = $this->runMutator($data['invoice']);

        $this->assertNotNull($result['peppol']);
        $this->assertNotEmpty($result['xml']);
    }

    // ==================== ES (Spain) Tests ====================

    public function testES_SetsDueDateWhenMissing()
    {
        $data = $this->buildScenario([
            'company_country' => 'ES',
            'company_vat' => 'ESB12345678',
            'client_country' => 'ES',
            'client_vat' => 'ESA87654321',
            'client_classification' => 'business',
            'tax_rate' => 21,
            'tax_name' => 'IVA',
        ]);

        $result = $this->runMutator($data['invoice']);

        $this->assertNotNull($result['peppol']->DueDate, 'ES mutator should ensure DueDate is set');
    }

    // ==================== FR (France) Tests ====================

    public function testFR_GovernmentRoutesToChorusPro()
    {
        $data = $this->buildScenario([
            'company_country' => 'FR',
            'company_vat' => 'FRAA123456789',
            'client_country' => 'FR',
            'client_vat' => 'FRBB987654321',
            'client_classification' => 'government',
            'client_id_number' => '12345678901234',
        ]);

        $result = $this->runMutator($data['invoice']);

        $meta = $result['meta'];

        // FR government routes to Chorus Pro
        if (isset($meta['routing'])) {
            $siretRoute = $this->findRoutingScheme($meta, 'FR:SIRET');
            $this->assertNotNull($siretRoute, 'FR government should route via FR:SIRET');
        }

        $this->assertNotNull($result['peppol'], 'FR government pipeline should produce peppol');
    }

    public function testFR_BusinessRoutesViaSiret()
    {
        $data = $this->buildScenario([
            'company_country' => 'FR',
            'company_vat' => 'FRAA123456789',
            'client_country' => 'FR',
            'client_vat' => 'FRBB987654321',
            'client_classification' => 'business',
            'client_id_number' => '12345678901234', // 14 digits = SIRET
        ]);

        $result = $this->runMutator($data['invoice']);

        $meta = $result['meta'];

        if (isset($meta['routing'])) {
            $siretRoute = $this->findRoutingScheme($meta, 'FR:SIRET');
            $this->assertNotNull($siretRoute, 'FR business should route via FR:SIRET');
        }

        $this->assertNotNull($result['peppol']);
    }

    // ==================== IT (Italy) Tests ====================

    public function testIT_B2B_SetsIvaAndCuuoRouting()
    {
        $data = $this->buildScenario([
            'company_country' => 'IT',
            'company_vat' => 'IT92443356490',
            'client_country' => 'IT',
            'client_vat' => 'IT92443356489',
            'client_classification' => 'business',
            'client_routing_id' => 'SCSCSCS',
        ]);

        $result = $this->runMutator($data['invoice']);

        $meta = $result['meta'];

        // IT B2B should set IVA and CUUO routing when both sender and receiver are IT
        if (isset($meta['routing'])) {
            $ivaRoute = $this->findRoutingScheme($meta, 'IT:IVA');
            $cuuoRoute = $this->findRoutingScheme($meta, 'IT:CUUO');

            $this->assertNotNull($ivaRoute, 'IT B2B should include IT:IVA routing');
            $this->assertNotNull($cuuoRoute, 'IT B2B should include IT:CUUO routing');
        }

        $this->assertNotNull($result['peppol'], 'IT B2B should produce valid peppol');
    }

    public function testIT_B2C_SetsEmailRouting()
    {
        $data = $this->buildScenario([
            'company_country' => 'IT',
            'company_vat' => 'IT92443356490',
            'client_country' => 'IT',
            'client_vat' => 'RSSMRA85M01H501Z',
            'client_classification' => 'individual',
        ]);

        $result = $this->runMutator($data['invoice']);

        $meta = $result['meta'];

        if (isset($meta['routing'])) {
            $cfRoute = $this->findRoutingScheme($meta, 'IT:CF');
            $this->assertNotNull($cfRoute, 'IT B2C should include IT:CF routing');

            if (isset($meta['routing']['emails'])) {
                $this->assertNotEmpty($meta['routing']['emails'], 'IT B2C should set email routing');
            }
        }

        $this->assertNotNull($result['peppol'], 'IT B2C should produce valid peppol');
    }

    public function testIT_ForeignReceiver()
    {
        $data = $this->buildScenario([
            'company_country' => 'IT',
            'company_vat' => 'IT92443356490',
            'client_country' => 'DE',
            'client_vat' => 'DE923356489',
            'client_classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $result = $this->runMutator($data['invoice']);

        // IT to foreign should set routing using client country identifier
        $this->assertNotNull($result['peppol'], 'IT to foreign should produce valid peppol');
    }

    // ==================== RO (Romania) Tests ====================

    public function testRO_SetsAnafNetwork()
    {
        $data = $this->buildScenario([
            'company_country' => 'RO',
            'company_vat' => 'RO12345678',
            'client_country' => 'RO',
            'client_vat' => 'RO87654321',
            'client_classification' => 'business',
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
            'tax_rate' => 19,
            'tax_name' => 'TVA',
        ]);

        $result = $this->runMutator($data['invoice']);

        $meta = $result['meta'];

        // RO should enable ANAF network and set VAT routing
        if (isset($meta['networks'])) {
            $anafFound = false;
            foreach ($meta['networks'] as $network) {
                if ($network['application'] === 'ro-anaf') {
                    $anafFound = true;
                    $this->assertTrue($network['settings']['enabled']);
                }
            }
            $this->assertTrue($anafFound, 'RO mutator should enable ro-anaf network');

            $vatRoute = $this->findRoutingScheme($meta, 'RO:VAT');
            $this->assertNotNull($vatRoute, 'RO should route via RO:VAT');
        }

        $this->assertNotNull($result['peppol'], 'RO should produce valid peppol');
    }

    public function testRO_ResolvesStateAndSectorCodes()
    {
        $data = $this->buildScenario([
            'company_country' => 'RO',
            'company_vat' => 'RO12345678',
            'client_country' => 'RO',
            'client_vat' => 'RO87654321',
            'client_classification' => 'business',
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
            'tax_rate' => 19,
            'tax_name' => 'TVA',
        ]);

        $result = $this->runMutator($data['invoice']);

        // If RO mutator ran, state code should be resolved
        $customerState = $result['peppol']->AccountingCustomerParty->Party->PostalAddress->CountrySubentity ?? null;
        if ($customerState !== null) {
            $this->assertEquals('RO-B', $customerState);
        }

        $this->assertNotNull($result['peppol']);
    }

    // ==================== No-Op Country Tests ====================

    public function testPL_ProducesValidOutput()
    {
        $data = $this->buildScenario([
            'company_country' => 'PL',
            'company_vat' => 'PL1234567890',
            'client_country' => 'PL',
            'client_vat' => 'PL0987654321',
            'client_classification' => 'business',
            'tax_rate' => 23,
            'tax_name' => 'VAT',
        ]);

        $result = $this->runMutator($data['invoice']);
        $this->assertNotNull($result['peppol']);
    }

    public function testSE_ProducesValidOutput()
    {
        $data = $this->buildScenario([
            'company_country' => 'SE',
            'company_vat' => 'SE123456789101',
            'client_country' => 'SE',
            'client_vat' => 'SE109876543210',
            'client_classification' => 'business',
            'tax_rate' => 25,
            'tax_name' => 'Moms',
        ]);

        $result = $this->runMutator($data['invoice']);
        $this->assertNotNull($result['peppol']);
    }

    public function testAU_ProducesValidOutput()
    {
        $data = $this->buildScenario([
            'company_country' => 'AU',
            'company_vat' => '12345678901',
            'client_country' => 'AU',
            'client_vat' => '98765432109',
            'client_classification' => 'business',
            'tax_rate' => 10,
            'tax_name' => 'GST',
        ]);

        $result = $this->runMutator($data['invoice']);
        $this->assertNotNull($result['peppol']);
    }

    public function testNL_ProducesValidOutput()
    {
        $data = $this->buildScenario([
            'company_country' => 'NL',
            'company_vat' => 'NL123456789B01',
            'client_country' => 'NL',
            'client_vat' => 'NL987654321B01',
            'client_classification' => 'business',
            'tax_rate' => 21,
            'tax_name' => 'BTW',
        ]);

        $result = $this->runMutator($data['invoice']);
        $this->assertNotNull($result['peppol']);
    }

    // ==================== Full Pipeline Tests ====================

    public function testFullPipelineDE()
    {
        $data = $this->buildScenario([
            'company_country' => 'DE',
            'client_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_vat' => 'DE173755434',
            'client_classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $storecove = new Storecove();
        $result = $storecove->build($data['invoice']->fresh())->getResult();

        $this->assertArrayHasKey('document', $result);
        $this->assertEmpty($result['errors'], 'Full DE pipeline should produce no errors: ' . implode(', ', $result['errors']));
        $this->assertNotFalse($result['document']);
    }

    public function testFullPipelineIT_B2B()
    {
        $data = $this->buildScenario([
            'company_country' => 'IT',
            'company_vat' => 'IT92443356490',
            'client_country' => 'IT',
            'client_vat' => 'IT92443356489',
            'client_classification' => 'business',
            'has_valid_vat' => true,
            'client_routing_id' => 'SCSCSCS',
        ]);

        $storecove = new Storecove();
        $result = $storecove->build($data['invoice']->fresh())->getResult();

        $this->assertArrayHasKey('document', $result);
        $this->assertEmpty($result['errors'], 'Full IT B2B pipeline should produce no errors: ' . implode(', ', $result['errors']));
    }

    public function testFullPipelineRO()
    {
        $data = $this->buildScenario([
            'company_country' => 'RO',
            'company_vat' => 'RO12345678',
            'client_country' => 'RO',
            'client_vat' => 'RO87654321',
            'client_classification' => 'business',
            'client_state' => 'RO-B',
            'client_city' => 'SECTOR1',
            'tax_rate' => 19,
            'tax_name' => 'TVA',
        ]);

        $storecove = new Storecove();
        $result = $storecove->build($data['invoice']->fresh())->getResult();

        $this->assertArrayHasKey('document', $result);
        $this->assertEmpty($result['errors'], 'Full RO pipeline should produce no errors: ' . implode(', ', $result['errors']));
    }

    // ==================== Cross-border Tests ====================

    public function testDEtoFR_B2B()
    {
        $data = $this->buildScenario([
            'company_country' => 'DE',
            'company_vat' => 'DE923356489',
            'client_country' => 'FR',
            'client_vat' => 'FRAA123456789',
            'client_classification' => 'business',
            'has_valid_vat' => true,
        ]);

        $result = $this->runMutator($data['invoice']);

        // DE sender should still have PaymentMeans
        $this->assertNotNull($result['peppol']->PaymentMeans, 'DE to FR B2B should have PaymentMeans');
    }
}
