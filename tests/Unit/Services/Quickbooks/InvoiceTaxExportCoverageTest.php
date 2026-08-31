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

use App\DataMapper\ClientSync;
use App\DataMapper\QuickbooksSettings;
use App\Exceptions\QuickbooksMissingTaxCode;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Services\Quickbooks\Helpers\Helper;
use App\Services\Quickbooks\Models\QbProduct;
use App\Services\Quickbooks\Models\QbTaxRate;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\TaxCodeComponentKey;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Public-path guards for Ninja→QB tax export (future TaxExportContext / InvoiceTaxCodeResolver /
 * TxnTaxDetailBuilder extraction). Prefer ninjaToQb assertions over private reflection.
 */
class InvoiceTaxExportCoverageTest extends TestCase
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

    public function test_ninja_to_qb_composite_tax_uses_numeric_tax_code_ref(): void
    {
        $composite_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
                ['id' => 'r2', 'name' => 'PST', 'rate' => 7, 'tax_code_id' => '41'],
            ],
            'composite_tax_code_map' => [
                $composite_key => [
                    ['tax_code_id' => 'GST_PST_BC', 'name' => 'GST/PST BC'],
                ],
            ],
        ], $this->lineItemWithTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]));

        $this->assertSame('GST_PST_BC', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame('TaxExcluded', $qb_data['GlobalTaxCalculation']);
        $this->assertArrayNotHasKey('TxnTaxDetail', $qb_data);
    }

    public function test_ninja_to_qb_three_component_composite_tax_code_ref(): void
    {
        $composite_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
            ['name' => 'LEVY', 'rate' => 1],
        ]);

        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
                ['id' => 'r2', 'name' => 'PST', 'rate' => 7, 'tax_code_id' => '41'],
                ['id' => 'r3', 'name' => 'LEVY', 'rate' => 1, 'tax_code_id' => '42'],
            ],
            'composite_tax_code_map' => [
                $composite_key => [
                    ['tax_code_id' => 'GST_PST_LEVY', 'name' => 'GST/PST/Levy'],
                ],
            ],
        ], $this->lineItemWithTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
            ['name' => 'LEVY', 'rate' => 1],
        ]));

        $this->assertSame('GST_PST_LEVY', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_ninja_to_qb_throws_quickbooks_missing_tax_code_for_unresolved_composite(): void
    {
        $this->expectException(QuickbooksMissingTaxCode::class);
        $this->expectExceptionMessage('QuickBooks requires a TaxCode for taxes');

        $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
                ['id' => 'r2', 'name' => 'PST', 'rate' => 7, 'tax_code_id' => '41'],
            ],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]), expect_company_sync_refresh: true);
    }

    public function test_ninja_to_qb_throws_quickbooks_missing_tax_code_for_unresolved_single_rate(): void
    {
        $this->expectException(QuickbooksMissingTaxCode::class);

        $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([
            ['name' => 'QST', 'rate' => 9.975],
        ]), expect_company_sync_refresh: true);
    }

    public function test_ninja_to_qb_us_forces_tax_non_even_when_settings_store_numeric_codes(): void
    {
        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'US',
            'automatic_taxes' => false,
            'default_taxable_code' => '123',
            'default_exempt_code' => '456',
            'tax_rate_map' => [
                // getTaxMap() appends " {rate}%" to the line tax name
                ['id' => 'r1', 'name' => 'State Tax 6%', 'rate' => 6, 'tax_code_id' => '123'],
            ],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([
            ['name' => 'State Tax', 'rate' => 6],
        ]));

        $this->assertSame('TAX', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertArrayHasKey('TxnTaxDetail', $qb_data);
    }

    public function test_ninja_to_qb_non_us_falls_back_when_exempt_code_is_literal_non(): void
    {
        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => 'NON',
            'tax_rate_map' => [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
            ],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([]));

        $this->assertSame('40', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_ninja_to_qb_exempt_tax_id_uses_exempt_code(): void
    {
        $line = $this->lineItemWithTaxes([]);
        $line->tax_id = '5';

        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
            ],
            'composite_tax_code_map' => [],
        ], $line);

        $this->assertSame('9', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_ninja_to_qb_ast_non_us_uses_default_taxable_code_without_txn_tax_detail(): void
    {
        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => true,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
            ],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([
            ['name' => 'GST', 'rate' => 5],
        ]));

        $this->assertSame('40', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame('TaxExcluded', $qb_data['GlobalTaxCalculation']);
        $this->assertArrayNotHasKey('TxnTaxDetail', $qb_data);
    }

    public function test_ninja_to_qb_us_ast_omits_txn_tax_detail_and_uses_tax_literal(): void
    {
        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'US',
            'automatic_taxes' => true,
            'default_taxable_code' => 'TAX',
            'default_exempt_code' => 'NON',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([
            ['name' => 'State', 'rate' => 6],
        ]));

        $this->assertSame('TAX', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame('TaxExcluded', $qb_data['GlobalTaxCalculation']);
        $this->assertArrayNotHasKey('TxnTaxDetail', $qb_data);
    }

    public function test_ninja_to_qb_invoice_level_taxes_merge_into_line_tax_code_ref(): void
    {
        $line = InvoiceItemFactory::create();
        $line->product_key = 'Widget';
        $line->quantity = 1;
        $line->cost = 100;
        $line->line_total = 100;
        $line->notes = 'Widget';
        $line->tax_id = '1';

        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [
                ['id' => 'r1', 'name' => 'HST', 'rate' => 13, 'tax_code_id' => 'HST_CODE'],
            ],
            'composite_tax_code_map' => [],
        ], $line, invoice_level_taxes: [
            ['name' => 'HST', 'rate' => 13],
        ]);

        $this->assertSame('HST_CODE', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_ninja_to_qb_us_manual_txn_tax_detail_structure(): void
    {
        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'US',
            'automatic_taxes' => false,
            'default_taxable_code' => 'TAX',
            'default_exempt_code' => 'NON',
            'tax_rate_map' => [
                ['id' => 'qb-rate-6', 'name' => 'State Tax 6%', 'rate' => 6, 'tax_code_id' => 'TAX'],
            ],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([
            ['name' => 'State Tax', 'rate' => 6],
        ]));

        $this->assertArrayHasKey('TxnTaxDetail', $qb_data);
        $this->assertArrayHasKey('TotalTax', $qb_data['TxnTaxDetail']);
        $this->assertArrayHasKey('TaxLine', $qb_data['TxnTaxDetail']);
        $this->assertNotEmpty($qb_data['TxnTaxDetail']['TaxLine']);
        $this->assertSame(
            'qb-rate-6',
            $qb_data['TxnTaxDetail']['TaxLine'][0]['TaxLineDetail']['TaxRateRef']['value']
        );
        $this->assertTrue($qb_data['TxnTaxDetail']['TaxLine'][0]['TaxLineDetail']['PercentBased']);
        $this->assertSame(6.0, $qb_data['TxnTaxDetail']['TaxLine'][0]['TaxLineDetail']['TaxPercent']);
    }

    public function test_ninja_to_qb_us_manual_txn_tax_detail_null_when_no_line_taxes(): void
    {
        $qb_data = $this->ninjaToQbWithSettings([
            'country' => 'US',
            'automatic_taxes' => false,
            'default_taxable_code' => 'TAX',
            'default_exempt_code' => 'NON',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ], $this->lineItemWithTaxes([]));

        $this->assertSame('NON', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertArrayNotHasKey('TxnTaxDetail', $qb_data);
    }

    public function test_composite_lookup_parity_between_resolver_and_qb_tax_rate(): void
    {
        $components = [
            ['name' => 'GST', 'rate' => 5.0],
            ['name' => 'QST', 'rate' => 9.975],
        ];
        $component_key = TaxCodeComponentKey::fromComponents($components);

        $company = $this->company;
        $company->quickbooks = new QuickbooksSettings([
            'settings' => [
                'tax_rate_map' => [],
                'composite_tax_code_map' => [
                    $component_key => [
                        ['tax_code_id' => 'GST_QST_QC', 'name' => 'GST/QST QC'],
                    ],
                ],
            ],
        ]);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $company;

        $resolver_method = new ReflectionMethod(InvoiceTransformer::class, 'findCompositeTaxCodeId');
        $resolver_method->setAccessible(true);
        $resolver_id = $resolver_method->invoke(
            new InvoiceTransformer($company),
            $components,
            $company->quickbooks->settings->composite_tax_code_map
        );

        $qb_tax_rate_method = new ReflectionMethod(QbTaxRate::class, 'findExistingTaxCodeId');
        $qb_tax_rate_method->setAccessible(true);
        $qb_tax_rate_id = $qb_tax_rate_method->invoke(new QbTaxRate($service), $components);

        $this->assertSame('GST_QST_QC', $resolver_id);
        $this->assertSame($resolver_id, $qb_tax_rate_id);
    }

    public function test_composite_lookup_parity_ambiguous_candidates_return_null_on_both_paths(): void
    {
        $components = [
            ['name' => 'GST', 'rate' => 5.0],
            ['name' => 'PST', 'rate' => 7.0],
        ];
        $component_key = TaxCodeComponentKey::fromComponents($components);

        $company = $this->company;
        $company->quickbooks = new QuickbooksSettings([
            'settings' => [
                'tax_rate_map' => [],
                'composite_tax_code_map' => [
                    $component_key => [
                        ['tax_code_id' => 'A', 'name' => 'One'],
                        ['tax_code_id' => 'B', 'name' => 'Two'],
                    ],
                ],
            ],
        ]);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $company;

        $resolver_method = new ReflectionMethod(InvoiceTransformer::class, 'findCompositeTaxCodeId');
        $resolver_method->setAccessible(true);
        $resolver_id = $resolver_method->invoke(
            new InvoiceTransformer($company),
            $components,
            $company->quickbooks->settings->composite_tax_code_map
        );

        $qb_tax_rate_method = new ReflectionMethod(QbTaxRate::class, 'findExistingTaxCodeId');
        $qb_tax_rate_method->setAccessible(true);
        $qb_tax_rate_id = $qb_tax_rate_method->invoke(new QbTaxRate($service), $components);

        $this->assertNull($resolver_id);
        $this->assertNull($qb_tax_rate_id);
    }

    public function test_find_tax_code_id_by_rate_fuzzy_name_and_rate_only_fallback(): void
    {
        $transformer = new InvoiceTransformer($this->company);
        $method = new ReflectionMethod(InvoiceTransformer::class, 'findTaxCodeIdByRate');
        $method->setAccessible(true);

        $map = [
            ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => 'GST_CODE'],
            ['id' => 'r2', 'name' => 'PST', 'rate' => 7, 'tax_code_id' => 'PST_CODE'],
        ];

        $this->assertSame('GST_CODE', $method->invoke($transformer, $map, 5.0, 'GST 5%'));
        $this->assertSame('PST_CODE', $method->invoke($transformer, $map, 7.0, 'Unrelated Name'));
        $this->assertNull($method->invoke($transformer, $map, 9.975, 'QST'));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<int, array{name: string, rate: float|int}>|null  $invoice_level_taxes
     * @return array<string, mixed>
     */
    private function ninjaToQbWithSettings(
        array $settings,
        object $line_item,
        bool $expect_company_sync_refresh = false,
        ?array $invoice_level_taxes = null,
    ): array {
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

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->sync = new ClientSync(['qb_id' => 'CUST-1']);
        $client->save();

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'email' => 'tax-export@gmail.com',
            'is_primary' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'TAX-EXP-' . uniqid(),
            'date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => true,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'line_items' => [$line_item],
        ]);

        if ($invoice_level_taxes) {
            foreach ($invoice_level_taxes as $index => $tax) {
                $slot = $index + 1;
                $invoice->{"tax_name{$slot}"} = $tax['name'];
                $invoice->{"tax_rate{$slot}"} = $tax['rate'];
            }
            $invoice->saveQuietly();
        }

        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();
        $invoice->load(['client.contacts']);

        $product = Mockery::mock(QbProduct::class);
        $product->shouldReceive('findOrCreateProduct')->andReturn('ITEM-1');

        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getDiscountAccountId')->andReturn(null);
        $helper->shouldReceive('cleanHtmlText')->andReturnUsing(fn (string $text): string => $text);

        $tax_rate = Mockery::mock(QbTaxRate::class);
        $tax_rate->shouldReceive('ensureTaxCodeForComponents')->andReturn(null);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $this->company->fresh();
        $service->product = $product;
        $service->helper = $helper;
        $service->tax_rate = $tax_rate;

        if ($expect_company_sync_refresh) {
            $service->shouldReceive('companySync')->once()->andReturnSelf();
        } else {
            $service->shouldReceive('companySync')->never();
        }

        return (new InvoiceTransformer($this->company))->ninjaToQb($invoice->fresh(['client.contacts']), $service);
    }

    /**
     * @param  array<int, array{name: string, rate: float|int}>  $taxes
     */
    private function lineItemWithTaxes(array $taxes): object
    {
        $line = InvoiceItemFactory::create();
        $line->product_key = 'Widget';
        $line->quantity = 1;
        $line->cost = 100;
        $line->line_total = 100;
        $line->notes = 'Widget';
        $line->tax_id = '1';

        foreach ($taxes as $index => $tax) {
            $slot = $index + 1;
            $line->{"tax_name{$slot}"} = $tax['name'];
            $line->{"tax_rate{$slot}"} = $tax['rate'];
        }

        return $line;
    }
}
