<?php

namespace Tests\Feature\Quickbooks;

use App\DataMapper\QuickbooksSettings;
use App\Exceptions\QuickbooksMissingTaxCode;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Quickbooks\Models\QbTaxRate;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\TaxCodeComponentKey;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use Mockery;
use QuickBooksOnline\API\Data\IPPTaxService;
use QuickBooksOnline\API\DataService\DataService;
use ReflectionMethod;
use Tests\TestCase;

class InvoiceTransformerCompositeTaxTest extends TestCase
{
    private InvoiceTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transformer = new InvoiceTransformer(new Company());
    }

    public function test_invoice_level_two_component_tax_resolves_to_composite_tax_code(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'PST', 'rate' => 7],
            ['name' => 'GST', 'rate' => 5],
        ]);

        $composite_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $tax_code_id = $this->resolveLineTaxCode($line_item, [
            $composite_key => [
                ['tax_code_id' => 'GST_PST_BC', 'name' => 'GST/PST BC'],
            ],
        ]);

        $this->assertSame('GST_PST_BC', $tax_code_id);
    }

    public function test_invoice_level_three_component_tax_resolves_to_composite_tax_code(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
            ['name' => 'LEVY', 'rate' => 1],
        ]);

        $composite_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'LEVY', 'rate' => 1],
            ['name' => 'PST', 'rate' => 7],
            ['name' => 'GST', 'rate' => 5],
        ]);

        $tax_code_id = $this->resolveLineTaxCode($line_item, [
            $composite_key => [
                ['tax_code_id' => 'GST_PST_LEVY', 'name' => 'GST/PST/Levy'],
            ],
        ]);

        $this->assertSame('GST_PST_LEVY', $tax_code_id);
    }

    public function test_single_tax_still_uses_single_rate_path(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'GST', 'rate' => 5],
        ]);

        $tax_code_id = $this->resolveLineTaxCode($line_item);

        $this->assertSame('GST_CODE', $tax_code_id);
    }

    public function test_missing_composite_tax_code_blocks_invoice_push(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $this->expectException(QuickbooksMissingTaxCode::class);
        $this->expectExceptionMessage('QuickBooks requires a TaxCode for taxes');

        $this->resolveLineTaxCode($line_item, []);
    }

    public function test_duplicate_same_key_composite_candidates_with_same_id_are_accepted(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $composite_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $tax_code_id = $this->resolveLineTaxCode($line_item, [
            $composite_key => [
                ['tax_code_id' => 'GST_PST_BC', 'name' => 'GST/PST BC'],
                ['tax_code_id' => 'GST_PST_BC', 'name' => 'GST and PST BC'],
            ],
        ]);

        $this->assertSame('GST_PST_BC', $tax_code_id);
    }

    public function test_ambiguous_same_key_composite_candidates_with_different_ids_block_invoice_push(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $composite_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $this->expectException(QuickbooksMissingTaxCode::class);
        $this->expectExceptionMessage('QuickBooks requires a TaxCode for taxes');

        $this->resolveLineTaxCode($line_item, [
            $composite_key => [
                ['tax_code_id' => 'GST_PST_BC', 'name' => 'GST/PST BC'],
                ['tax_code_id' => 'OTHER_12', 'name' => 'Other 12%'],
            ],
        ]);
    }

    public function test_no_tax_uses_exempt_code(): void
    {
        $tax_code_id = $this->resolveLineTaxCode($this->lineItem());

        $this->assertSame('EXEMPT_CODE', $tax_code_id);
    }

    public function test_unresolved_composite_tax_key_requests_lazy_tax_code_refresh(): void
    {
        $components = [
            ['name' => 'GST', 'rate' => 5.0],
            ['name' => 'QST', 'rate' => 9.975],
        ];
        $component_key = TaxCodeComponentKey::fromComponents($components);

        $this->assertSame([$component_key => $components], $this->unresolvedTaxCodeComponents($this->invoiceWithTaxes($components), []));
    }

    public function test_resolved_composite_tax_key_skips_lazy_tax_code_refresh(): void
    {
        $components = [
            ['name' => 'GST', 'rate' => 5.0],
            ['name' => 'QST', 'rate' => 9.975],
        ];
        $component_key = TaxCodeComponentKey::fromComponents($components);

        $this->assertSame([], $this->unresolvedTaxCodeComponents($this->invoiceWithTaxes($components), [
            $component_key => [
                ['tax_code_id' => 'GST_QST_QC', 'name' => 'GST/QST QC'],
            ],
        ]));
    }

    public function test_resolved_single_tax_does_not_request_lazy_tax_code_refresh(): void
    {
        $invoice = $this->invoiceWithTaxes([
            ['name' => 'GST', 'rate' => 5],
        ]);

        $this->assertSame([], $this->unresolvedTaxCodeComponents($invoice, []));
    }

    public function test_unresolved_single_tax_requests_lazy_tax_code_creation(): void
    {
        $components = [
            ['name' => 'QST', 'rate' => 9.975],
        ];
        $component_key = TaxCodeComponentKey::fromComponents($components);

        $this->assertSame([$component_key => $components], $this->unresolvedTaxCodeComponents($this->invoiceWithTaxes($components), []));
    }

    public function test_missing_single_tax_code_blocks_invoice_push(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $this->expectException(QuickbooksMissingTaxCode::class);
        $this->expectExceptionMessage('QuickBooks requires a TaxCode for taxes');

        $this->resolveLineTaxCode($line_item, []);
    }

    public function test_exempt_line_does_not_request_lazy_tax_code_refresh(): void
    {
        $line_item = $this->lineItem();
        $line_item->tax_id = '5';
        $invoice = $this->invoiceWithTaxes([
            ['name' => 'GST', 'rate' => 5.0],
            ['name' => 'QST', 'rate' => 9.975],
        ], [$line_item]);

        $this->assertSame([], $this->unresolvedTaxCodeComponents($invoice, []));
    }

    public function test_us_multi_component_tax_still_uses_tax_code_literal(): void
    {
        $line_item = $this->lineItemWithInvoiceTaxes([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'PST', 'rate' => 7],
        ]);

        $method = new ReflectionMethod(InvoiceTransformer::class, 'resolveLineTaxCodeUS');
        $method->setAccessible(true);

        $this->assertSame('TAX', $method->invoke($this->transformer, $line_item, 'TAX', 'NON'));
    }

    public function test_qb_tax_rate_creates_tax_service_for_missing_components(): void
    {
        $company = new Company();
        $company->quickbooks = new QuickbooksSettings([
            'settings' => [
                'tax_rate_map' => [],
                'composite_tax_code_map' => [],
            ],
        ]);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $company;

        $sdk = Mockery::mock(DataService::class);
        $service->sdk = $sdk;
        $tax_service_payload = null;

        $sdk->shouldReceive('Query')
            ->once()
            ->with('SELECT * FROM TaxAgency')
            ->andReturn([
                (object) ['Id' => '10', 'DisplayName' => 'Receiver General'],
                (object) ['Id' => '20', 'DisplayName' => 'Revenu Quebec'],
            ]);

        $sdk->shouldReceive('Add')
            ->once()
            ->with(Mockery::on(function (mixed $payload) use (&$tax_service_payload): bool {
                $tax_service_payload = $payload;

                return $payload instanceof IPPTaxService;
            }))
            ->andReturn((object) ['TaxService' => (object) ['TaxCodeId' => '999']]);

        $tax_code_id = (new QbTaxRate($service))->ensureTaxCodeForComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $component_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $this->assertSame('999', $tax_code_id);
        $this->assertSame('GST 5% + QST 9.975%', $tax_service_payload->TaxCode);
        $this->assertSame('10', $tax_service_payload->TaxRateDetails[0]->TaxAgencyId);
        $this->assertSame('20', $tax_service_payload->TaxRateDetails[1]->TaxAgencyId);
        $this->assertSame('999', $company->quickbooks->settings->composite_tax_code_map[$component_key][0]['tax_code_id']);
    }

    public function test_qb_tax_rate_creates_missing_tax_agency_for_component(): void
    {
        $company = new Company();
        $company->quickbooks = new QuickbooksSettings([
            'settings' => [
                'tax_rate_map' => [],
                'composite_tax_code_map' => [],
            ],
        ]);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $company;

        $sdk = Mockery::mock(DataService::class);
        $service->sdk = $sdk;
        $tax_service_payload = null;

        $sdk->shouldReceive('Query')
            ->once()
            ->with('SELECT * FROM TaxAgency')
            ->andReturn([
                (object) ['Id' => '10', 'DisplayName' => 'Receiver General'],
            ]);

        $sdk->shouldReceive('Add')
            ->once()
            ->with(Mockery::on(fn (mixed $payload): bool => data_get($payload, 'DisplayName') === 'Revenu Quebec'))
            ->andReturn((object) ['Id' => '20'])
            ->ordered();

        $sdk->shouldReceive('Add')
            ->once()
            ->with(Mockery::on(function (mixed $payload) use (&$tax_service_payload): bool {
                $tax_service_payload = $payload;

                return $payload instanceof IPPTaxService;
            }))
            ->andReturn((object) ['TaxService' => (object) ['TaxCodeId' => '999']])
            ->ordered();

        $tax_code_id = (new QbTaxRate($service))->ensureTaxCodeForComponents([
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $this->assertSame('999', $tax_code_id);
        $this->assertSame('20', $tax_service_payload->TaxRateDetails[0]->TaxAgencyId);
        $this->assertSame('999', $company->quickbooks->settings->tax_rate_map[0]['tax_code_id']);
        $this->assertSame('QST', $company->quickbooks->settings->tax_rate_map[0]['name']);
    }

    public function test_component_key_strips_generated_quickbooks_rate_suffix_from_names(): void
    {
        $this->assertSame(
            TaxCodeComponentKey::fromComponents([
                ['name' => 'GST', 'rate' => 5],
                ['name' => 'QST', 'rate' => 9.975],
            ]),
            TaxCodeComponentKey::fromComponents([
                ['name' => 'GST 5%', 'rate' => 5],
                ['name' => 'QST 9.975%', 'rate' => 9.975],
            ])
        );
    }

    public function test_qb_tax_rate_reuses_existing_tax_code_after_duplicate_name_response(): void
    {
        $company = new Company();
        $company->quickbooks = new QuickbooksSettings([
            'settings' => [
                'tax_rate_map' => [],
                'composite_tax_code_map' => [],
            ],
        ]);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $company;

        $sdk = Mockery::mock(DataService::class);
        $service->sdk = $sdk;
        $first_payload = null;

        $sdk->shouldReceive('Query')
            ->once()
            ->with('SELECT * FROM TaxAgency')
            ->andReturn([
                (object) ['Id' => '10', 'DisplayName' => 'Receiver General'],
                (object) ['Id' => '20', 'DisplayName' => 'Revenu Quebec'],
            ]);

        $sdk->shouldReceive('Add')
            ->once()
            ->with(Mockery::on(function (mixed $payload) use (&$first_payload): bool {
                $first_payload = $payload;

                return $payload instanceof IPPTaxService;
            }))
            ->andThrow(new \Exception('Duplicate Name Exists Error code":"6240"'));

        $service->shouldReceive('fetchTaxRates')
            ->once()
            ->andReturn([
                ['id' => '101', 'name' => 'GST 5%', 'rate' => 5],
                ['id' => '102', 'name' => 'QST 9.975%', 'rate' => 9.975],
            ]);

        $service->shouldReceive('fetchTaxCodes')
            ->once()
            ->andReturn([
                [
                    'Id' => ['value' => '999'],
                    'Name' => 'GST 5% + QST 9.975%',
                    'SalesTaxRateList' => [
                        'TaxRateDetail' => [
                            ['TaxRateRef' => ['value' => '101']],
                            ['TaxRateRef' => ['value' => '102']],
                        ],
                    ],
                ],
            ]);

        $tax_code_id = (new QbTaxRate($service))->ensureTaxCodeForComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $component_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $this->assertSame('999', $tax_code_id);
        $this->assertSame('GST 5% + QST 9.975%', $first_payload->TaxCode);
        $this->assertSame('999', $company->quickbooks->settings->composite_tax_code_map[$component_key][0]['tax_code_id']);
        $this->assertSame('ninja', $company->quickbooks->settings->composite_tax_code_map[$component_key][0]['source']);
    }

    public function test_qb_tax_rate_retries_tax_service_creation_with_generated_names_when_duplicate_is_not_matching_tax_code(): void
    {
        $company = new Company();
        $company->quickbooks = new QuickbooksSettings([
            'settings' => [
                'tax_rate_map' => [],
                'composite_tax_code_map' => [],
            ],
        ]);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $company;

        $sdk = Mockery::mock(DataService::class);
        $service->sdk = $sdk;
        $first_payload = null;
        $retry_payload = null;

        $sdk->shouldReceive('Query')
            ->twice()
            ->with('SELECT * FROM TaxAgency')
            ->andReturn([
                (object) ['Id' => '10', 'DisplayName' => 'Receiver General'],
                (object) ['Id' => '20', 'DisplayName' => 'Revenu Quebec'],
            ]);

        $sdk->shouldReceive('Add')
            ->once()
            ->with(Mockery::on(function (mixed $payload) use (&$first_payload): bool {
                $first_payload = $payload;

                return $payload instanceof IPPTaxService;
            }))
            ->andThrow(new \Exception('Duplicate Name Exists Error code":"6240"'))
            ->ordered();

        $service->shouldReceive('fetchTaxRates')
            ->once()
            ->andReturn([
                ['id' => '101', 'name' => 'GST 5%', 'rate' => 5],
                ['id' => '102', 'name' => 'QST 9.975%', 'rate' => 9.975],
            ]);

        $service->shouldReceive('fetchTaxCodes')
            ->once()
            ->andReturn([]);

        $sdk->shouldReceive('Add')
            ->once()
            ->with(Mockery::on(function (mixed $payload) use (&$retry_payload): bool {
                $retry_payload = $payload;

                return $payload instanceof IPPTaxService;
            }))
            ->andReturn((object) ['TaxService' => (object) ['TaxCodeId' => '999']])
            ->ordered();

        $tax_code_id = (new QbTaxRate($service))->ensureTaxCodeForComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $component_key = TaxCodeComponentKey::fromComponents([
            ['name' => 'GST', 'rate' => 5],
            ['name' => 'QST', 'rate' => 9.975],
        ]);

        $this->assertSame('999', $tax_code_id);
        $this->assertSame('GST 5% + QST 9.975%', $first_payload->TaxCode);
        $this->assertStringStartsWith('Ninja Tax ', $retry_payload->TaxCode);
        $this->assertNotSame($first_payload->TaxCode, $retry_payload->TaxCode);
        $this->assertSame('101', $retry_payload->TaxRateDetails[0]->TaxRateId);
        $this->assertSame('102', $retry_payload->TaxRateDetails[1]->TaxRateId);
        $this->assertSame('999', $company->quickbooks->settings->composite_tax_code_map[$component_key][0]['tax_code_id']);
    }

    public function test_quickbooks_settings_serializes_composite_tax_code_map(): void
    {
        $map = [
            'gst:5.0000|pst:7.0000' => [
                ['tax_code_id' => 'GST_PST_BC', 'name' => 'GST/PST BC'],
            ],
        ];

        $settings = new QuickbooksSettings([
            'settings' => [
                'composite_tax_code_map' => $map,
            ],
        ]);

        $array = $settings->toArray();
        $round_trip = QuickbooksSettings::fromArray($array);

        $this->assertSame($map, $array['settings']['composite_tax_code_map']);
        $this->assertSame($map, $round_trip->settings->composite_tax_code_map);
    }

    /**
     * @param  array<int, array{name: string, rate: float|int}>  $invoice_taxes
     */
    private function lineItemWithInvoiceTaxes(array $invoice_taxes): object
    {
        $invoice = $this->invoiceWithTaxes($invoice_taxes);

        $extract_method = new ReflectionMethod(InvoiceTransformer::class, 'extractInvoiceLevelTaxes');
        $extract_method->setAccessible(true);
        $invoice_level_taxes = $extract_method->invoke($this->transformer, $invoice);

        $merge_method = new ReflectionMethod(InvoiceTransformer::class, 'mergeInvoiceLevelTaxes');
        $merge_method->setAccessible(true);

        return $merge_method->invoke($this->transformer, $this->lineItem(), $invoice_level_taxes);
    }

    /**
     * @param  array<int, array{name: string, rate: float|int}>  $invoice_taxes
     * @param  array<int, object>|null  $line_items
     */
    private function invoiceWithTaxes(array $invoice_taxes, ?array $line_items = null): Invoice
    {
        $invoice = new Invoice();

        foreach ($invoice_taxes as $index => $tax) {
            $slot = $index + 1;
            $invoice->{"tax_name{$slot}"} = $tax['name'];
            $invoice->{"tax_rate{$slot}"} = $tax['rate'];
        }

        $invoice->line_items = $line_items ?? [$this->lineItem()];

        return $invoice;
    }

    private function lineItem(): object
    {
        return (object) [
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
    }

    private function resolveLineTaxCode(object $line_item, array $composite_tax_code_map = []): string
    {
        $method = new ReflectionMethod(InvoiceTransformer::class, 'resolveLineTaxCode');
        $method->setAccessible(true);

        return $method->invoke(
            $this->transformer,
            $line_item,
            $this->taxRateMap(),
            $composite_tax_code_map,
            'TAXABLE_CODE',
            'EXEMPT_CODE'
        );
    }

    /**
     * @return array<string, array<int, array{name: string, rate: float}>>
     */
    private function unresolvedTaxCodeComponents(Invoice $invoice, array $composite_tax_code_map): array
    {
        $extract_method = new ReflectionMethod(InvoiceTransformer::class, 'extractInvoiceLevelTaxes');
        $extract_method->setAccessible(true);
        $invoice_level_taxes = $extract_method->invoke($this->transformer, $invoice);

        $method = new ReflectionMethod(InvoiceTransformer::class, 'unresolvedTaxCodeComponents');
        $method->setAccessible(true);

        return $method->invoke($this->transformer, $invoice, $invoice_level_taxes, $this->taxRateMap(), $composite_tax_code_map);
    }

    private function taxRateMap(): array
    {
        return [
            ['id' => 'gst-rate', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => 'GST_CODE'],
            ['id' => 'pst-rate', 'name' => 'PST', 'rate' => 7, 'tax_code_id' => 'PST_CODE'],
            ['id' => 'levy-rate', 'name' => 'LEVY', 'rate' => 1, 'tax_code_id' => 'LEVY_CODE'],
        ];
    }
}