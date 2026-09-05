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
use App\DataMapper\InvoiceSync;
use App\DataMapper\QuickbooksSettings;
use App\Exceptions\QuickbooksMissingTaxCode;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\Location;
use App\Services\Quickbooks\Helpers\Helper;
use App\Services\Quickbooks\Models\QbClient;
use App\Services\Quickbooks\Models\QbProduct;
use App\Services\Quickbooks\Models\QbTaxRate;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use RuntimeException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Public-path characterization for InvoiceTransformer ninjaToQb / qbToNinja
 * before extracting TaxExportContext, resolvers, and pure mappers.
 */
class InvoiceTransformerMappingCharacterizationTest extends TestCase
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

    public function test_qb_to_ninja_is_an_alias_of_transform(): void
    {
        $client = $this->makeLinkedClient('CUST-ALIAS');
        $qb_data = $this->qbInvoicePayload(customer_ref: 'CUST-ALIAS');

        $transformer = new InvoiceTransformer($this->company);

        $this->assertSame(
            $transformer->transform($qb_data),
            $transformer->qbToNinja($qb_data)
        );
    }

    public function test_qb_to_ninja_without_service_maps_header_fields_and_skips_helper_io(): void
    {
        $client = $this->makeLinkedClient('CUST-HEADER');
        $qb_data = $this->qbInvoicePayload(
            customer_ref: 'CUST-HEADER',
            overrides: [
                'Id' => 'QB-88',
                'DocNumber' => 'INV-0088',
                'TxnDate' => '2026-02-01',
                'DueDate' => '2026-02-15',
                'PrivateNote' => 'internal',
                'CustomerMemo' => 'thanks',
                'PONumber' => 'PO-9',
                'Deposit' => 12.5,
                'Balance' => 87.5,
            ]
        );

        $result = (new InvoiceTransformer($this->company))->qbToNinja($qb_data);

        $this->assertIsArray($result);
        $this->assertSame('QB-88', $result['id']);
        $this->assertSame($client->id, $result['client_id']);
        $this->assertSame('INV-0088', $result['number']);
        $this->assertSame('2026-02-01', $result['date']);
        $this->assertSame('internal', $result['private_notes']);
        $this->assertSame('thanks', $result['public_notes']);
        $this->assertSame('2026-02-15', $result['due_date']);
        $this->assertSame('PO-9', $result['po_number']);
        $this->assertSame(12.5, $result['partial']);
        $this->assertSame([], $result['line_items']);
        $this->assertSame([], $result['payment_ids']);
        $this->assertSame(Invoice::STATUS_SENT, $result['status_id']);
        $this->assertSame(0, $result['custom_surcharge1']);
        $this->assertSame(87.5, $result['balance']);
    }

    public function test_qb_to_ninja_without_service_defaults_missing_optional_fields(): void
    {
        $this->makeLinkedClient('CUST-DEFAULTS');

        $result = (new InvoiceTransformer($this->company))->qbToNinja([
            'CustomerRef' => 'CUST-DEFAULTS',
        ]);

        $this->assertIsArray($result);
        $this->assertFalse($result['id']);
        $this->assertFalse($result['number']);
        $this->assertSame(now()->format('Y-m-d'), $result['date']);
        $this->assertSame('', $result['private_notes']);
        $this->assertFalse($result['public_notes']);
        $this->assertNull($result['due_date']);
        $this->assertSame('', $result['po_number']);
        $this->assertSame(0.0, $result['partial']);
        $this->assertSame(0, $result['balance']);
    }

    public function test_qb_to_ninja_returns_false_when_client_cannot_be_resolved(): void
    {
        $result = (new InvoiceTransformer($this->company))->qbToNinja([
            'Id' => 'QB-MISSING',
            'CustomerRef' => 'NO-SUCH-CUSTOMER',
        ]);

        $this->assertFalse($result);
    }

    public function test_qb_to_ninja_with_service_delegates_client_lines_payments_and_surcharge(): void
    {
        $line_items = [(object) ['product_key' => 'Widget', 'cost' => 10]];
        $qb_data = $this->qbInvoicePayload(customer_ref: 'CUST-SVC', overrides: ['Id' => 'QB-1']);

        $qb_client = Mockery::mock(QbClient::class);
        $qb_client->shouldReceive('findOrCreateClient')->once()->with('CUST-SVC')->andReturn(4242);

        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('calculateTotalTax')->once()->with($qb_data)->andReturn([8.25, 'Sales Tax']);
        $helper->shouldReceive('checkIfDiscountAfterTax')->once()->with($qb_data)->andReturn(-15.0);
        $helper->shouldReceive('getLineItems')->once()->with($qb_data, [8.25, 'Sales Tax'])->andReturn($line_items);
        $helper->shouldReceive('getPayments')->once()->with($qb_data)->andReturn(['PAY-1', 'PAY-2']);

        $service = Mockery::mock(QuickbooksService::class);
        $service->client = $qb_client;
        $service->helper = $helper;

        $result = (new InvoiceTransformer($this->company))->qbToNinja($qb_data, $service);

        $this->assertIsArray($result);
        $this->assertSame(4242, $result['client_id']);
        $this->assertSame($line_items, $result['line_items']);
        $this->assertSame(['PAY-1', 'PAY-2'], $result['payment_ids']);
        $this->assertSame(-15.0, $result['custom_surcharge1']);
        $this->assertSame('QB-1', $result['id']);
    }

    public function test_qb_to_ninja_with_service_returns_false_when_find_or_create_client_fails(): void
    {
        $qb_client = Mockery::mock(QbClient::class);
        $qb_client->shouldReceive('findOrCreateClient')->once()->with('CUST-GONE')->andReturn(null);

        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('calculateTotalTax')->andReturn([0, '']);
        $helper->shouldReceive('checkIfDiscountAfterTax')->andReturn(0);

        $service = Mockery::mock(QuickbooksService::class);
        $service->client = $qb_client;
        $service->helper = $helper;

        $this->assertFalse((new InvoiceTransformer($this->company))->qbToNinja([
            'Id' => 'QB-GONE',
            'CustomerRef' => 'CUST-GONE',
        ], $service));
    }

    public function test_ninja_to_qb_maps_header_metadata_and_truncates_qb_limits(): void
    {
        $long_email = str_repeat('a', 95) . '@example.com';
        $long_notes = str_repeat('n', 4100);
        $long_terms = str_repeat('t', 50);
        $long_private = str_repeat('p', 4100);
        $long_number = 'DOC-' . str_repeat('9', 30);
        $long_po = str_repeat('P', 40);

        $invoice = $this->makeInvoice([
            'number' => $long_number,
            'date' => '2026-04-01',
            'due_date' => '2026-04-30',
            'public_notes' => $long_notes,
            'terms' => $long_terms,
            'private_notes' => '<p>' . $long_private . '</p>',
            'po_number' => $long_po,
            'partial' => 25.5,
            'amount' => 100,
        ], [$this->lineItem()], email: $long_email);

        $invoice->sync = new InvoiceSync(qb_id: 'QB-INV-7');
        $invoice->saveQuietly();

        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getDiscountAccountId')->andReturn(null);
        $helper->shouldReceive('cleanHtmlText')->andReturnUsing(function (string $text): string {
            return trim(strip_tags($text));
        });

        $qb_data = $this->ninjaToQb($invoice, helper: $helper);

        $this->assertSame('CUST-1', $qb_data['CustomerRef']['value']);
        $this->assertSame(100, mb_strlen($qb_data['BillEmail']['Address']));
        $this->assertSame('2026-04-01', $qb_data['TxnDate']);
        $this->assertSame('2026-04-30', $qb_data['DueDate']);
        $this->assertSame(21, mb_strlen($qb_data['DocNumber']));
        $this->assertTrue($qb_data['ApplyTaxAfterDiscount']);
        $this->assertSame('NeedToPrint', $qb_data['PrintStatus']);
        $this->assertSame('NotSet', $qb_data['EmailStatus']);
        $this->assertSame(25, mb_strlen($qb_data['PONumber']));
        $this->assertEquals(25.5, $qb_data['Deposit']);
        $this->assertSame('QB-INV-7', $qb_data['Id']);
        $this->assertSame(1000, mb_strlen($qb_data['CustomerMemo']['value']));
        $this->assertSame(4000, mb_strlen($qb_data['PrivateNote']));
        $this->assertArrayNotHasKey('GlobalTaxCalculation', $qb_data);
    }

    public function test_ninja_to_qb_omits_optional_fields_when_empty_and_has_no_id_for_unlinked_invoice(): void
    {
        $invoice = $this->makeInvoice([
            'public_notes' => '',
            'terms' => '',
            'private_notes' => '',
            'po_number' => '',
            'partial' => 0,
        ], [$this->lineItem()]);

        $qb_data = $this->ninjaToQb($invoice);

        $this->assertArrayNotHasKey('CustomerMemo', $qb_data);
        $this->assertArrayNotHasKey('PrivateNote', $qb_data);
        $this->assertArrayNotHasKey('PONumber', $qb_data);
        $this->assertArrayNotHasKey('Deposit', $qb_data);
        $this->assertArrayNotHasKey('Id', $qb_data);
        $this->assertArrayNotHasKey('ShipAddr', $qb_data);
    }

    public function test_ninja_to_qb_includes_location_ship_address(): void
    {
        $invoice = $this->makeInvoice([], [$this->lineItem()]);

        $location = Location::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $invoice->client_id,
            'address1' => '123 Long Street Name That Exceeds Forty One Characters',
            'address2' => 'Suite 100',
            'city' => 'Springfield',
            'state' => 'IL',
            'postal_code' => '62701',
            'country_id' => 840,
        ]);

        $invoice->location_id = $location->id;
        $invoice->saveQuietly();

        $qb_data = $this->ninjaToQb($invoice->fresh(['client.contacts', 'location.country']));

        $this->assertSame(
            mb_substr('123 Long Street Name That Exceeds Forty One Characters', 0, 41),
            $qb_data['ShipAddr']['Line1']
        );
        $this->assertSame('Suite 100', $qb_data['ShipAddr']['Line2']);
        $this->assertSame('Springfield', $qb_data['ShipAddr']['City']);
        $this->assertSame('IL', $qb_data['ShipAddr']['CountrySubDivisionCode']);
        $this->assertSame('62701', $qb_data['ShipAddr']['PostalCode']);
        $this->assertSame($location->country->iso_3166_3, $qb_data['ShipAddr']['Country']);
    }

    public function test_ninja_to_qb_sales_line_uses_quantity_cost_notes_and_item_ref(): void
    {
        $line = $this->lineItem();
        $line->quantity = 3;
        $line->cost = 12.5;
        $line->line_total = 37.5;
        $line->notes = str_repeat('d', 4010);
        $line->product_key = 'Widget';

        $invoice = $this->makeInvoice([], [$line]);
        $qb_data = $this->ninjaToQb($invoice, product_qb_id: 'ITEM-99');

        $this->assertCount(1, $qb_data['Line']);
        $this->assertSame(1, $qb_data['Line'][0]['LineNum']);
        $this->assertSame('SalesItemLineDetail', $qb_data['Line'][0]['DetailType']);
        $this->assertSame('ITEM-99', $qb_data['Line'][0]['SalesItemLineDetail']['ItemRef']['value']);
        $this->assertSame(3, $qb_data['Line'][0]['SalesItemLineDetail']['Qty']);
        $this->assertSame(12.5, $qb_data['Line'][0]['SalesItemLineDetail']['UnitPrice']);
        $this->assertSame('NON', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame(4000, mb_strlen($qb_data['Line'][0]['Description']));
        $this->assertSame(37.5, $qb_data['Line'][0]['Amount']);
    }

    public function test_ninja_to_qb_skips_empty_product_ids_and_rethrows_missing_tax_code(): void
    {
        $kept = $this->lineItem();
        $kept->product_key = 'Keep';
        $skipped = $this->lineItem();
        $skipped->product_key = 'Skip';

        $invoice = $this->makeInvoice([], [$skipped, $kept]);

        $product = Mockery::mock(QbProduct::class);
        $product->shouldReceive('findOrCreateProduct')
            ->twice()
            ->andReturn('', 'ITEM-KEEP');

        $qb_data = $this->ninjaToQb($invoice, product: $product);

        $this->assertCount(1, $qb_data['Line']);
        $this->assertSame('ITEM-KEEP', $qb_data['Line'][0]['SalesItemLineDetail']['ItemRef']['value']);
        $this->assertSame(1, $qb_data['Line'][0]['LineNum']);
    }

    public function test_ninja_to_qb_skips_line_when_product_lookup_throws_generic_exception(): void
    {
        $kept = $this->lineItem();
        $kept->product_key = 'Keep';
        $broken = $this->lineItem();
        $broken->product_key = 'Broken';

        $invoice = $this->makeInvoice([], [$broken, $kept]);

        $product = Mockery::mock(QbProduct::class);
        $product->shouldReceive('findOrCreateProduct')
            ->twice()
            ->andReturnUsing(function (object $line_item): string {
                if (($line_item->product_key ?? '') === 'Broken') {
                    throw new RuntimeException('item create failed');
                }

                return 'ITEM-KEEP';
            });

        $qb_data = $this->ninjaToQb($invoice, product: $product);

        $this->assertCount(1, $qb_data['Line']);
        $this->assertSame('ITEM-KEEP', $qb_data['Line'][0]['SalesItemLineDetail']['ItemRef']['value']);
    }

    public function test_ninja_to_qb_rethrows_missing_tax_code_from_line_build(): void
    {
        $line = $this->lineItemWithTaxes([['name' => 'QST', 'rate' => 9.975]]);
        $invoice = $this->makeInvoice([], [$line], settings: [
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ]);

        $this->expectException(QuickbooksMissingTaxCode::class);

        $this->ninjaToQb($invoice, expect_company_sync_refresh: true, settings: [
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ]);
    }

    public function test_ninja_to_qb_empty_lines_throws_when_every_product_is_skipped(): void
    {
        $invoice = $this->makeInvoice([], [$this->lineItem()]);

        $product = Mockery::mock(QbProduct::class);
        $product->shouldReceive('findOrCreateProduct')->once()->andReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('no valid line items could be processed');

        $this->ninjaToQb($invoice, product: $product);
    }

    public function test_ninja_to_qb_amount_discount_line_uses_zero_percent_and_account_ref(): void
    {
        $invoice = $this->makeInvoice([
            'discount' => 10,
            'is_amount_discount' => true,
        ], [$this->lineItem(cost: 100, quantity: 1)]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getDiscountAccountId')->once()->andReturn('DISC-88');
        $helper->shouldReceive('cleanHtmlText')->andReturnUsing(fn (string $text): string => $text);

        $qb_data = $this->ninjaToQb($invoice, helper: $helper);

        $discount = $qb_data['Line'][1];
        $this->assertSame('DiscountLineDetail', $discount['DetailType']);
        $this->assertSame(10.0, $discount['Amount']);
        $this->assertFalse($discount['DiscountLineDetail']['PercentBased']);
        $this->assertSame(0.0, $discount['DiscountLineDetail']['DiscountPercent']);
        $this->assertSame('DISC-88', $discount['DiscountLineDetail']['DiscountAccountRef']['value']);
        $this->assertSame(2, $discount['LineNum']);
    }

    public function test_ninja_to_qb_percentage_discount_line_sets_percent_and_omits_account_when_missing(): void
    {
        $invoice = $this->makeInvoice([
            'discount' => 15,
            'is_amount_discount' => false,
        ], [$this->lineItem(cost: 100, quantity: 1)]);

        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $helper = Mockery::mock(Helper::class);
        $helper->shouldReceive('getDiscountAccountId')->once()->andReturn(null);
        $helper->shouldReceive('cleanHtmlText')->andReturnUsing(fn (string $text): string => $text);

        $qb_data = $this->ninjaToQb($invoice, helper: $helper);

        $discount = $qb_data['Line'][1];
        $this->assertTrue($discount['DiscountLineDetail']['PercentBased']);
        $this->assertSame(15.0, $discount['DiscountLineDetail']['DiscountPercent']);
        $this->assertArrayNotHasKey('DiscountAccountRef', $discount['DiscountLineDetail']);
    }

    public function test_ninja_to_qb_exempt_tax_id_eight_uses_exempt_code(): void
    {
        $line = $this->lineItem();
        $line->tax_id = '8';

        $invoice = $this->makeInvoice([], [$line], settings: [
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ]);

        $qb_data = $this->ninjaToQb($invoice, settings: [
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ]);

        $this->assertSame('9', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_ninja_to_qb_company_sync_refresh_can_resolve_previously_missing_tax_code(): void
    {
        $line = $this->lineItemWithTaxes([['name' => 'GST', 'rate' => 5]]);
        $settings = [
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ];

        $invoice = $this->makeInvoice([], [$line], settings: $settings);

        $service = $this->makeService($settings);
        $service->shouldReceive('companySync')->once()->andReturnUsing(function () use ($service) {
            $service->company->quickbooks->settings->tax_rate_map = [
                ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
            ];

            return $service;
        });
        $service->tax_rate->shouldReceive('ensureTaxCodeForComponents')->never();

        $qb_data = (new InvoiceTransformer($this->company))->ninjaToQb($invoice, $service);

        $this->assertSame('40', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_ninja_to_qb_company_sync_failure_continues_and_provisions_tax_code(): void
    {
        $line = $this->lineItemWithTaxes([['name' => 'GST', 'rate' => 5]]);
        $settings = [
            'country' => 'CA',
            'automatic_taxes' => false,
            'default_taxable_code' => '40',
            'default_exempt_code' => '9',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ];

        $invoice = $this->makeInvoice([], [$line], settings: $settings);
        $service = $this->makeService($settings);
        $service->shouldReceive('companySync')->once()->andThrow(new RuntimeException('sync down'));
        $service->tax_rate->shouldReceive('ensureTaxCodeForComponents')
            ->once()
            ->andReturnUsing(function () use ($service) {
                $service->company->quickbooks->settings->tax_rate_map = [
                    ['id' => 'r1', 'name' => 'GST', 'rate' => 5, 'tax_code_id' => '40'],
                ];

                return '40';
            });

        $qb_data = (new InvoiceTransformer($this->company))->ninjaToQb($invoice, $service);

        $this->assertSame('40', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
    }

    public function test_ninja_to_qb_us_txn_tax_detail_provisions_missing_tax_rate_then_builds_detail(): void
    {
        $line = $this->lineItemWithTaxes([['name' => 'State Tax', 'rate' => 6]]);
        $settings = [
            'country' => 'US',
            'automatic_taxes' => false,
            'default_taxable_code' => 'TAX',
            'default_exempt_code' => 'NON',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ];

        $invoice = $this->makeInvoice([], [$line], settings: $settings);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $service = $this->makeService($settings);
        $service->shouldReceive('companySync')->never();
        $service->tax_rate->shouldReceive('ensureTaxCodeForComponents')
            ->once()
            ->andReturnUsing(function () use ($service) {
                $service->company->quickbooks->settings->tax_rate_map = [
                    ['id' => 'qb-rate-6', 'name' => 'State Tax 6%', 'rate' => 6, 'tax_code_id' => 'TAX'],
                ];

                return 'TAX';
            });

        $qb_data = (new InvoiceTransformer($this->company))->ninjaToQb($invoice->fresh(['client.contacts']), $service);

        $this->assertSame('TAX', $qb_data['Line'][0]['SalesItemLineDetail']['TaxCodeRef']['value']);
        $this->assertSame('qb-rate-6', $qb_data['TxnTaxDetail']['TaxLine'][0]['TaxLineDetail']['TaxRateRef']['value']);
    }

    public function test_ninja_to_qb_us_txn_tax_detail_throws_when_tax_rate_remains_unavailable(): void
    {
        $line = $this->lineItemWithTaxes([['name' => 'State Tax', 'rate' => 6]]);
        $settings = [
            'country' => 'US',
            'automatic_taxes' => false,
            'default_taxable_code' => 'TAX',
            'default_exempt_code' => 'NON',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ];

        $invoice = $this->makeInvoice([], [$line], settings: $settings);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->saveQuietly();

        $service = $this->makeService($settings);
        $service->shouldReceive('companySync')->never();
        $service->tax_rate->shouldReceive('ensureTaxCodeForComponents')->once()->andReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('QuickBooks TaxRate unavailable');

        (new InvoiceTransformer($this->company))->ninjaToQb($invoice->fresh(['client.contacts']), $service);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, object>  $line_items
     * @param  array<string, mixed>  $settings
     */
    private function makeInvoice(array $attributes, array $line_items, array $settings = [], string $email = 'map-char@gmail.com'): Invoice
    {
        $this->applySettings($settings);

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
            'email' => $email,
            'is_primary' => true,
        ]);

        $invoice = Invoice::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'MAP-CHAR-' . uniqid(),
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
            'public_notes' => '',
            'private_notes' => '',
            'terms' => '',
            'po_number' => '',
            'partial' => 0,
            'line_items' => $line_items,
        ], $attributes));

        return $invoice->fresh(['client.contacts']);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function ninjaToQb(
        Invoice $invoice,
        array $settings = [],
        ?QbProduct $product = null,
        ?Helper $helper = null,
        string $product_qb_id = 'ITEM-1',
        bool $expect_company_sync_refresh = false,
    ): array {
        $service = $this->makeService($settings, $product, $helper, $product_qb_id);

        if ($expect_company_sync_refresh) {
            $service->shouldReceive('companySync')->once()->andReturnSelf();
        } else {
            $service->shouldReceive('companySync')->never();
        }

        return (new InvoiceTransformer($this->company))->ninjaToQb($invoice->fresh(['client.contacts']), $service);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function makeService(
        array $settings = [],
        ?QbProduct $product = null,
        ?Helper $helper = null,
        string $product_qb_id = 'ITEM-1',
    ): QuickbooksService {
        $this->applySettings($settings);

        if (!$product) {
            $product = Mockery::mock(QbProduct::class);
            $product->shouldReceive('findOrCreateProduct')->andReturn($product_qb_id);
        }

        if (!$helper) {
            $helper = Mockery::mock(Helper::class);
            $helper->shouldReceive('getDiscountAccountId')->andReturn(null);
            $helper->shouldReceive('cleanHtmlText')->andReturnUsing(fn (string $text): string => $text);
        }

        $tax_rate = Mockery::mock(QbTaxRate::class);
        $tax_rate->shouldReceive('ensureTaxCodeForComponents')->byDefault()->andReturn(null);

        $service = Mockery::mock(QuickbooksService::class);
        $service->company = $this->company->fresh();
        $service->product = $product;
        $service->helper = $helper;
        $service->tax_rate = $tax_rate;

        return $service;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function applySettings(array $settings): void
    {
        $defaults = [
            'country' => 'US',
            'automatic_taxes' => false,
            'default_taxable_code' => 'TAX',
            'default_exempt_code' => 'NON',
            'tax_rate_map' => [],
            'composite_tax_code_map' => [],
        ];

        $this->company->quickbooks = new QuickbooksSettings([
            'accessTokenKey' => 'test-access-token',
            'refresh_token' => 'test-refresh-token',
            'realmID' => 'test-realm',
            'accessTokenExpiresAt' => time() + 3600,
            'refreshTokenExpiresAt' => time() + 86400,
            'baseURL' => 'https://sandbox-quickbooks.api.intuit.com',
            'companyName' => 'Test Company',
            'settings' => array_merge($defaults, $settings),
        ]);
        $this->company->save();
    }

    private function makeLinkedClient(string $qb_id): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);
        $client->sync = new ClientSync(['qb_id' => $qb_id]);
        $client->save();

        return $client;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function qbInvoicePayload(string $customer_ref, array $overrides = []): array
    {
        return array_merge([
            'Id' => 'QB-1',
            'CustomerRef' => $customer_ref,
            'DocNumber' => 'INV-1',
            'TxnDate' => '2026-01-15',
            'DueDate' => '2026-02-15',
            'PrivateNote' => '',
            'CustomerMemo' => '',
            'PONumber' => '',
            'Deposit' => 0,
            'Balance' => 0,
        ], $overrides);
    }

    private function lineItem(float $cost = 100, float $quantity = 1): object
    {
        $line = InvoiceItemFactory::create();
        $line->product_key = 'Widget';
        $line->quantity = $quantity;
        $line->cost = $cost;
        $line->line_total = $cost * $quantity;
        $line->notes = 'Widget';
        $line->tax_id = '1';

        return $line;
    }

    /**
     * @param  array<int, array{name: string, rate: float|int}>  $taxes
     */
    private function lineItemWithTaxes(array $taxes): object
    {
        $line = $this->lineItem();

        foreach ($taxes as $index => $tax) {
            $slot = $index + 1;
            $line->{"tax_name{$slot}"} = $tax['name'];
            $line->{"tax_rate{$slot}"} = $tax['rate'];
        }

        return $line;
    }
}
