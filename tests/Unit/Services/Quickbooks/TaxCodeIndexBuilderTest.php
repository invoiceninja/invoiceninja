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

namespace Tests\Unit\Services\Quickbooks;

use App\DataMapper\QuickbooksSettings;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\SdkWrapper;
use App\Services\Quickbooks\TaxCodeComponentKey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Guards companySync tax-index building (future TaxCodeIndexBuilder extraction).
 * Uses mocked SDK fetches — no live QuickBooks calls.
 */
class TaxCodeIndexBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->app['config']->set('services.quickbooks.client_id', null);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_company_sync_builds_composite_tax_code_map_for_two_components(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5],
                ['id' => 'r2', 'name' => 'PST', 'rate' => 7],
            ],
            tax_codes: [
                $this->taxCode('100', 'GST/PST', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                    ['TaxRateRef' => ['value' => 'r2']],
                ]),
            ],
        );

        $key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $this->assertArrayHasKey($key, $settings->composite_tax_code_map);
        $this->assertSame('100', $settings->composite_tax_code_map[$key][0]['tax_code_id']);
        $this->assertSame('GST/PST', $settings->composite_tax_code_map[$key][0]['name']);
    }

    public function test_company_sync_builds_composite_tax_code_map_for_three_components(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5],
                ['id' => 'r2', 'name' => 'PST', 'rate' => 7],
                ['id' => 'r3', 'name' => 'LEVY', 'rate' => 1],
            ],
            tax_codes: [
                $this->taxCode('200', 'GST/PST/Levy', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                    ['TaxRateRef' => ['value' => 'r2']],
                    ['TaxRateRef' => ['value' => 'r3']],
                ]),
            ],
        );

        $key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
            ['name' => 'LEVY', 'rate' => 1],
        ]);

        $this->assertArrayHasKey($key, $settings->composite_tax_code_map);
        $this->assertSame('200', $settings->composite_tax_code_map[$key][0]['tax_code_id']);
    }

    public function test_company_sync_augments_tax_rate_map_with_tax_code_id(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'HST', 'rate' => 13],
            ],
            tax_codes: [
                $this->taxCode('50', 'HST ON', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                ]),
            ],
        );

        $hst = collect($settings->tax_rate_map)->firstWhere('id', 'r1');

        $this->assertNotNull($hst);
        $this->assertSame('50', $hst['tax_code_id']);
        $this->assertSame('50', $settings->default_taxable_code);
    }

    public function test_company_sync_us_country_forces_tax_and_non_defaults(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'US',
            tax_rates: [
                ['id' => 'r1', 'name' => 'State', 'rate' => 6],
            ],
            tax_codes: [
                $this->taxCode('1', 'TAX', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                ]),
                $this->taxCode('2', 'NON', false, []),
            ],
        );

        $this->assertSame('US', $settings->country);
        $this->assertSame('TAX', $settings->default_taxable_code);
        $this->assertSame('NON', $settings->default_exempt_code);
    }

    public function test_company_sync_non_us_uses_numeric_default_codes(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'HST', 'rate' => 13],
                ['id' => 'r2', 'name' => 'GST', 'rate' => 5],
            ],
            tax_codes: [
                $this->taxCode('50', 'HST', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                ]),
                $this->taxCode('40', 'GST', true, [
                    ['TaxRateRef' => ['value' => 'r2']],
                ]),
                $this->taxCode('9', 'Exempt', false, []),
            ],
        );

        $this->assertSame('CA', $settings->country);
        $this->assertSame('50', $settings->default_taxable_code, 'Highest non-zero sales rate TaxCode wins');
        $this->assertSame('9', $settings->default_exempt_code);
    }

    public function test_company_sync_resolves_exempt_code_by_common_name_when_not_marked_non_taxable(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5],
            ],
            tax_codes: [
                $this->taxCode('40', 'GST', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                ]),
                // Taxable flag true-ish missing / ambiguous — name fallback still finds "exempt"
                [
                    'Id' => '88',
                    'Name' => 'Tax Exempt',
                    'Taxable' => true,
                    'SalesTaxRateList' => ['TaxRateDetail' => []],
                ],
            ],
        );

        $this->assertSame('88', $settings->default_exempt_code);
    }

    public function test_company_sync_preserves_and_merges_ninja_source_tax_rate_map_entries(): void
    {
        $this->seedCompanyQuickbooks([
            'country' => 'CA',
            'tax_rate_map' => [
                [
                    'id' => 'ninja-rate-1',
                    'name' => 'Custom Ninja Tax',
                    'rate' => 2.5,
                    'tax_code_id' => 'NINJA_TC',
                    'source' => 'ninja',
                ],
            ],
            'composite_tax_code_map' => [],
        ]);

        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5],
            ],
            tax_codes: [
                $this->taxCode('40', 'GST', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                ]),
                $this->taxCode('9', 'Exempt', false, []),
            ],
            seed_settings_first: false,
        );

        $ninja_entry = collect($settings->tax_rate_map)->firstWhere('id', 'ninja-rate-1');

        $this->assertNotNull($ninja_entry);
        $this->assertSame('ninja', $ninja_entry['source']);
        $this->assertSame('NINJA_TC', $ninja_entry['tax_code_id']);
    }

    public function test_company_sync_merges_ninja_source_composite_map_entries(): void
    {
        $key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $this->seedCompanyQuickbooks([
            'country' => 'CA',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [
                $key => [
                    [
                        'tax_code_id' => 'NINJA_COMP',
                        'name' => 'Ninja GST+PST',
                        'source' => 'ninja',
                    ],
                ],
            ],
        ]);

        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5],
                ['id' => 'r2', 'name' => 'PST', 'rate' => 7],
            ],
            tax_codes: [
                $this->taxCode('100', 'GST/PST', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                    ['TaxRateRef' => ['value' => 'r2']],
                ]),
                $this->taxCode('9', 'Exempt', false, []),
            ],
            seed_settings_first: false,
        );

        $candidates = $settings->composite_tax_code_map[$key] ?? [];
        $ids = collect($candidates)->pluck('tax_code_id')->all();

        $this->assertContains('100', $ids);
        $this->assertContains('NINJA_COMP', $ids);
    }

    public function test_company_sync_excludes_zero_rate_components_from_composite_key(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5],
                ['id' => 'r0', 'name' => 'Zero', 'rate' => 0],
            ],
            tax_codes: [
                $this->taxCode('40', 'GST only', true, [
                    ['TaxRateRef' => ['value' => 'r1']],
                    ['TaxRateRef' => ['value' => 'r0']],
                ]),
                $this->taxCode('9', 'Exempt', false, []),
            ],
        );

        // Only one positive-rate component → not a composite bucket
        $this->assertSame([], $settings->composite_tax_code_map);
        $gst = collect($settings->tax_rate_map)->firstWhere('id', 'r1');
        $this->assertSame('40', $gst['tax_code_id']);
    }

    public function test_company_sync_normalizes_single_tax_rate_detail_object(): void
    {
        $settings = $this->runCompanySyncWithTaxFixtures(
            country: 'CA',
            tax_rates: [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5],
            ],
            tax_codes: [
                [
                    'Id' => ['value' => '40'],
                    'Name' => 'GST',
                    'Taxable' => 'true',
                    'SalesTaxRateList' => [
                        'TaxRateDetail' => [
                            'TaxRateRef' => ['value' => 'r1'],
                        ],
                    ],
                ],
                $this->taxCode('9', 'Exempt', false, []),
            ],
        );

        $gst = collect($settings->tax_rate_map)->firstWhere('id', 'r1');
        $this->assertSame('40', $gst['tax_code_id']);
    }

    /**
     * @param  array<int, array{id: string, name: string, rate: float|int}>  $tax_rates
     * @param  array<int, array<string, mixed>>  $tax_codes
     */
    private function runCompanySyncWithTaxFixtures(
        string $country,
        array $tax_rates,
        array $tax_codes,
        bool $seed_settings_first = true,
    ): object {
        if ($seed_settings_first) {
            $this->seedCompanyQuickbooks([
                'country' => $country,
                'tax_rate_map' => [],
                'composite_tax_code_map' => [],
            ]);
        }

        $sdk = Mockery::mock(SdkWrapper::class);
        $sdk->shouldReceive('company')
            ->once()
            ->andReturn((object) [
                'CompanyName' => 'Tax Index Fixture Co',
                'CompanyAddr' => (object) ['Country' => $country],
            ]);
        $sdk->shouldReceive('getPreferences')
            ->once()
            ->andReturn((object) [
                'TaxPrefs' => (object) ['PartnerTaxEnabled' => false],
                'SalesFormsPrefs' => (object) ['AllowDeposit' => false],
            ]);

        $service = Mockery::mock(QuickbooksService::class, [$this->company->fresh()])->makePartial();
        $service->shouldReceive('sdk')->andReturn($sdk);
        $service->shouldReceive('fetchIncomeAccounts')->once()->andReturn([
            ['id' => 'inc-1', 'label' => 'Sales', 'account_type' => 'Income'],
        ]);
        $service->shouldReceive('fetchTaxRates')->once()->andReturn($tax_rates);
        $service->shouldReceive('fetchTaxCodes')->once()->andReturn($tax_codes);
        $service->shouldReceive('fetchPaymentMethods')->once()->andReturn([]);

        $service->companySync();

        return $this->company->fresh()->quickbooks->settings;
    }

    private function seedCompanyQuickbooks(array $settings): void
    {
        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => $settings,
        ]);
        $this->company->save();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rate_details
     * @return array<string, mixed>
     */
    private function taxCode(string $id, string $name, bool $taxable, array $rate_details): array
    {
        return [
            'Id' => $id,
            'Name' => $name,
            'Taxable' => $taxable,
            'SalesTaxRateList' => [
                'TaxRateDetail' => $rate_details,
            ],
        ];
    }
}
