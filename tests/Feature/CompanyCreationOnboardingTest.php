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

namespace Tests\Feature;

use App\DataMapper\CompanyDefaults\CountryDefaults;
use App\Jobs\Company\CreateCompany;
use App\Models\Account;
use App\Models\Company;
use App\Models\Country;
use App\Models\TaxRate;
use App\Models\User;
use Tests\MockAccountData;
use Tests\TestCase;

class CompanyCreationOnboardingTest extends TestCase
{
    use MockAccountData;

    /** @var array<int, Account> */
    private array $createdAccounts = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdAccounts as $account) {
            // Delete all companies, users, and tax rates tied to this account
            $companies = Company::where('account_id', $account->id)->get();
            foreach ($companies as $company) {
                TaxRate::where('company_id', $company->id)->forceDelete();
                $company->forceDelete();
            }
            User::where('account_id', $account->id)->forceDelete();
            $account->forceDelete();
        }

        parent::tearDown();
    }

    /**
     * Helper: run CreateCompany for a given country, then localizeCompany.
     * Returns the created Company with fresh DB state.
     */
    private function createCompanyForCountry(string $countryId): Company
    {
        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 10,
        ]);
        $this->createdAccounts[] = $account;

        // Stub the cf-ipcountry header so resolveCountry() picks up the country
        $country = Country::find($countryId);
        if ($country) {
            request()->headers->set('cf-ipcountry', $country->iso_3166_2);
        }

        $company = (new CreateCompany([
            'name' => 'Test Company',
        ], $account))->handle();

        $this->assertNotNull($company);
        $this->assertInstanceOf(Company::class, $company);

        // Create a user for localizeCompany
        $user = User::factory()->create([
            'account_id' => $account->id,
            'email' => fake()->unique()->safeEmail(),
        ]);

        // Clear any existing tax rates for this company
        TaxRate::where('company_id', $company->id)->forceDelete();

        $company->service()->localizeCompany($user);

        return $company->fresh();
    }

    // ── Australia (36) ──────────────────────────────────────────

    public function testAustraliaCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('36');

        $this->assertEquals('12', $company->settings->currency_id);  // AUD
        $this->assertEquals('105', $company->settings->timezone_id); // Australia/Sydney
        $this->assertEquals(1, $company->enabled_tax_rates);
        $this->assertEquals(1, $company->enabled_item_tax_rates);
        $this->assertEquals('Tax Invoice', $company->settings->translations->invoice ?? null);

        $taxRates = TaxRate::where('company_id', $company->id)->get();
        $this->assertCount(1, $taxRates);
        $this->assertEquals('GST', $taxRates->first()->name);
        $this->assertEquals(10, $taxRates->first()->rate);
    }

    // ── Germany (276) ──────────────────────────────────────────

    public function testGermanyCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('276');

        $this->assertEquals('3', $company->settings->currency_id);  // EUR
        $this->assertEquals('37', $company->settings->timezone_id); // Europe/Berlin
        $this->assertEquals(0, $company->enabled_tax_rates);
        $this->assertEquals(1, $company->enabled_item_tax_rates);

        $taxRates = TaxRate::where('company_id', $company->id)->pluck('rate', 'name')->toArray();
        $this->assertCount(2, $taxRates);
        $this->assertEquals(19, $taxRates['MwSt']);
        $this->assertEquals(7, $taxRates['MwSt (ermäßigt)']);
    }

    // ── Spain (724) ─────────────────────────────────────────────

    public function testSpainCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('724');

        $this->assertEquals('3', $company->settings->currency_id);  // EUR
        $this->assertEquals('43', $company->settings->timezone_id); // Europe/Madrid
        $this->assertEquals('7', $company->settings->language_id);
        $this->assertEquals('Facturae_3.2.2', $company->settings->e_invoice_type);
        $this->assertEquals(0, $company->enabled_tax_rates);
        $this->assertEquals(2, $company->enabled_item_tax_rates);

        // Custom fields
        $this->assertObjectHasProperty('contact1', $company->custom_fields);
        $this->assertObjectHasProperty('client1', $company->custom_fields);
        $this->assertStringContainsString('Rol|', $company->custom_fields->contact1);
        $this->assertEquals('Administración Pública|switch', $company->custom_fields->client1);

        // Tax rates: IVA standard + reduced + IRPF
        $taxRates = TaxRate::where('company_id', $company->id)->pluck('rate', 'name')->toArray();
        $this->assertCount(3, $taxRates);
        $this->assertEquals(21, $taxRates['IVA']);
        $this->assertEquals(10, $taxRates['IVA (reducido)']);
        $this->assertEquals(-15, $taxRates['IRPF']);
    }

    // ── South Africa (710) ──────────────────────────────────────

    public function testSouthAfricaCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('710');

        $this->assertEquals('4', $company->settings->currency_id);  // ZAR
        $this->assertEquals('56', $company->settings->timezone_id); // Africa/Harare
        $this->assertEquals(1, $company->enabled_tax_rates);
        $this->assertEquals(1, $company->enabled_item_tax_rates);
        $this->assertEquals('Tax Invoice', $company->settings->translations->invoice ?? null);

        $taxRates = TaxRate::where('company_id', $company->id)->get();
        $this->assertCount(1, $taxRates);
        $this->assertEquals('VAT', $taxRates->first()->name);
        $this->assertEquals(15, $taxRates->first()->rate);
    }

    // ── New Zealand (554) ───────────────────────────────────────

    public function testNewZealandCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('554');

        $this->assertEquals('15', $company->settings->currency_id);  // NZD
        $this->assertEquals('113', $company->settings->timezone_id); // Pacific/Auckland
        $this->assertEquals(1, $company->enabled_tax_rates);

        $taxRates = TaxRate::where('company_id', $company->id)->get();
        $this->assertCount(1, $taxRates);
        $this->assertEquals('GST', $taxRates->first()->name);
        $this->assertEquals(15, $taxRates->first()->rate);
    }

    // ── United Kingdom (826) ────────────────────────────────────

    public function testUnitedKingdomCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('826');

        $this->assertEquals('2', $company->settings->currency_id);  // GBP
        $this->assertEquals('33', $company->settings->timezone_id); // Europe/London
        $this->assertEquals(0, $company->enabled_tax_rates);
        $this->assertEquals(1, $company->enabled_item_tax_rates);

        $taxRates = TaxRate::where('company_id', $company->id)->pluck('rate', 'name')->toArray();
        $this->assertCount(2, $taxRates);
        $this->assertEquals(20, $taxRates['VAT']);
        $this->assertEquals(5, $taxRates['VAT (reduced)']);
    }

    // ── France (250) ────────────────────────────────────────────

    public function testFranceCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('250');

        $this->assertEquals('3', $company->settings->currency_id);  // EUR
        $this->assertEquals('44', $company->settings->timezone_id); // Europe/Paris

        $taxRates = TaxRate::where('company_id', $company->id)->pluck('rate', 'name')->toArray();
        $this->assertCount(2, $taxRates);
        $this->assertEquals(20, $taxRates['TVA']);
        $this->assertEquals(5.5, $taxRates['TVA (réduit)']);
    }

    // ── United States (840) ─────────────────────────────────────

    public function testUnitedStatesCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('840');

        $this->assertEquals('1', $company->settings->currency_id);  // USD
        $this->assertEquals('15', $company->settings->timezone_id); // America/New_York
        $this->assertEquals(1, $company->enabled_item_tax_rates);

        $taxRates = TaxRate::where('company_id', $company->id)->get();
        $this->assertCount(0, $taxRates);
    }

    // ── Unknown country ─────────────────────────────────────────

    public function testUnknownCountryCompanyCreation(): void
    {
        // Without cf-ipcountry header, falls back to US (840)
        request()->headers->remove('cf-ipcountry');

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 10,
        ]);
        $this->createdAccounts[] = $account;

        $company = (new CreateCompany([
            'name' => 'Test Company Unknown',
        ], $account))->handle();

        $this->assertNotNull($company);
        $this->assertEquals('840', $company->settings->country_id);
    }

    // ── Verify all configured countries have valid static IDs ────

    // ── Canada (124) - multi-rate ───────────────────────────────

    public function testCanadaCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('124');

        $this->assertEquals('9', $company->settings->currency_id);  // CAD
        $this->assertEquals('20', $company->settings->timezone_id); // America/Halifax

        $taxRates = TaxRate::where('company_id', $company->id)->pluck('rate', 'name')->toArray();
        $this->assertCount(3, $taxRates);
        $this->assertEquals(5, $taxRates['GST']);
        $this->assertEquals(9.975, $taxRates['QST']);
        $this->assertEquals(13, $taxRates['HST']);
    }

    // ── Italy (380) ─────────────────────────────────────────────

    public function testItalyCompanyCreation(): void
    {
        $company = $this->createCompanyForCountry('380');

        $this->assertEquals('3', $company->settings->currency_id);  // EUR
        $this->assertEquals('46', $company->settings->timezone_id); // Europe/Rome

        $taxRates = TaxRate::where('company_id', $company->id)->pluck('rate', 'name')->toArray();
        $this->assertCount(2, $taxRates);
        $this->assertEquals(22, $taxRates['IVA']);
        $this->assertEquals(10, $taxRates['IVA (ridotta)']);
    }

    // ── Full pipeline test for EVERY configured country ─────────

    public function testFullOnboardingPipelineForAllConfiguredCountries(): void
    {
        foreach (CountryDefaults::countries() as $countryId) {
            $defaults = CountryDefaults::get($countryId);
            $country = Country::find($countryId);
            $label = $country ? $country->name : $countryId;

            // ── 1. Run CreateCompany ────────────────────────────
            $account = Account::factory()->create([
                'hosted_client_count' => 1000,
                'hosted_company_count' => 10,
            ]);
            $this->createdAccounts[] = $account;

            if ($country) {
                request()->headers->set('cf-ipcountry', $country->iso_3166_2);
            }

            $company = (new CreateCompany([
                'name' => "Test {$label}",
            ], $account))->handle();

            $this->assertNotNull($company, "{$label}: CreateCompany returned null");
            $this->assertInstanceOf(Company::class, $company, "{$label}: not a Company instance");

            // ── 2. Verify country was set ───────────────────────
            $this->assertEquals(
                $countryId,
                $company->settings->country_id,
                "{$label}: country_id mismatch"
            );

            // ── 3. Verify currency_id matches config ────────────
            if ($defaults['currency_id']) {
                $this->assertEquals(
                    $defaults['currency_id'],
                    $company->settings->currency_id,
                    "{$label}: currency_id mismatch"
                );
            }

            // ── 4. Verify timezone_id matches config ────────────
            if ($defaults['timezone_id']) {
                $this->assertEquals(
                    $defaults['timezone_id'],
                    $company->settings->timezone_id,
                    "{$label}: timezone_id mismatch"
                );
            }

            // ── 5. Verify tax flags match config ────────────────
            $this->assertEquals(
                $defaults['enabled_tax_rates'],
                $company->enabled_tax_rates,
                "{$label}: enabled_tax_rates mismatch"
            );
            $this->assertEquals(
                $defaults['enabled_item_tax_rates'],
                $company->enabled_item_tax_rates,
                "{$label}: enabled_item_tax_rates mismatch"
            );

            // ── 6. Verify lock_invoices is NOT forced ───────────
            $this->assertNotEquals(
                'when_sent',
                $company->settings->lock_invoices ?? '',
                "{$label}: lock_invoices should not be forced"
            );

            // ── 7. Verify translations if configured ────────────
            if ($defaults['translations']) {
                foreach ($defaults['translations'] as $key => $value) {
                    $this->assertEquals(
                        $value,
                        $company->settings->translations->{$key} ?? null,
                        "{$label}: translation '{$key}' mismatch"
                    );
                }
            }

            // ── 8. Verify language_id if configured ─────────────
            if ($defaults['language_id']) {
                $this->assertEquals(
                    $defaults['language_id'],
                    $company->settings->language_id,
                    "{$label}: language_id mismatch"
                );
            }

            // ── 9. Verify e_invoice_type if configured ──────────
            if ($defaults['e_invoice_type']) {
                $this->assertEquals(
                    $defaults['e_invoice_type'],
                    $company->settings->e_invoice_type,
                    "{$label}: e_invoice_type mismatch"
                );
            }

            // ── 10. Verify custom_fields if configured ──────────
            if ($defaults['custom_fields']) {
                foreach ($defaults['custom_fields'] as $key => $value) {
                    $this->assertEquals(
                        $value,
                        $company->custom_fields->{$key} ?? null,
                        "{$label}: custom_field '{$key}' mismatch"
                    );
                }
            }

            // ── 11. Run localizeCompany and verify tax rates ────
            $user = User::factory()->create([
                'account_id' => $account->id,
                'email' => fake()->unique()->safeEmail(),
            ]);

            TaxRate::where('company_id', $company->id)->forceDelete();
            $company->service()->localizeCompany($user);

            $taxRates = TaxRate::where('company_id', $company->id)->get();

            $this->assertCount(
                count($defaults['tax_rates']),
                $taxRates,
                "{$label}: expected " . count($defaults['tax_rates']) . " tax rates, got " . $taxRates->count()
            );

            // Verify each configured tax rate was created with correct name and rate
            $dbRates = $taxRates->pluck('rate', 'name')->toArray();
            foreach ($defaults['tax_rates'] as $expectedTax) {
                $this->assertArrayHasKey(
                    $expectedTax['name'],
                    $dbRates,
                    "{$label}: missing tax rate '{$expectedTax['name']}'"
                );
                $this->assertEquals(
                    $expectedTax['rate'],
                    $dbRates[$expectedTax['name']],
                    "{$label}: tax rate '{$expectedTax['name']}' has wrong rate"
                );
            }
        }
    }
}
