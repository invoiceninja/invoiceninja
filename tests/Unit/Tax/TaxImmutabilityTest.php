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

namespace Tests\Unit\Tax;

use App\DataMapper\CompanySettings;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\Tax\ZipTax\Response;
use App\Factory\InvoiceFactory;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Guards against the `calculate_taxes` company setting
 * mutating invoices that must not change after the fact.
 *
 * @see \App\Models\Invoice::isTaxImmutable
 */
class TaxImmutabilityTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private array $resp = [
        'geoPostalCode' => '92582',
        'geoCity' => 'SAN JACINTO',
        'geoCounty' => 'RIVERSIDE',
        'geoState' => 'CA',
        'taxSales' => 0.0875,
        'taxUse' => 0.0875,
        'txbService' => 'N',
        'txbFreight' => 'N',
        'stateSalesTax' => 0.06,
        'stateUseTax' => 0.06,
        'citySalesTax' => 0.01,
        'cityUseTax' => 0.01,
        'cityTaxCode' => '874',
        'countySalesTax' => 0.0025,
        'countyUseTax' => 0.0025,
        'countyTaxCode' => '',
        'districtSalesTax' => 0.015,
        'districtUseTax' => 0.015,
        'district1Code' => '26',
        'district1SalesTax' => 0,
        'district1UseTax' => 0,
        'district2Code' => '26',
        'district2SalesTax' => 0.005,
        'district2UseTax' => 0.005,
        'district3Code' => '',
        'district3SalesTax' => 0,
        'district3UseTax' => 0,
        'district4Code' => '33',
        'district4SalesTax' => 0.01,
        'district4UseTax' => 0.01,
        'district5Code' => '',
        'district5SalesTax' => 0,
        'district5UseTax' => 0,
        'originDestination' => 'D',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->withoutExceptionHandling();

        $this->makeTestData();
    }

    private function buildInvoiceWithTaxableClient(int $status_id, bool $inclusive = false): Invoice
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '840';
        $settings->currency_id = '1';

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'CA';
        $tax_data->regions->US->has_sales_above_threshold = true;
        $tax_data->regions->US->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
            'origin_tax_data' => new Response($this->resp),
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 840,
            'postal_code' => '90210',
            'state' => 'CA',
            'tax_data' => new Response($this->resp),
        ]);

        $invoice = InvoiceFactory::create($company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->uses_inclusive_taxes = $inclusive;
        $invoice->status_id = $status_id;

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 10;
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->tax_id = Product::PRODUCT_TYPE_PHYSICAL;

        $invoice->line_items = [$line_item];
        $invoice->save();

        return $invoice;
    }

    public function testPaidInvoiceIsNotMutated(): void
    {
        $invoice = $this->buildInvoiceWithTaxableClient(Invoice::STATUS_PAID);

        $invoice = $invoice->calc()->getInvoice();
        $line_items = $invoice->line_items;

        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals('', $line_items[0]->tax_name1);
        $this->assertEquals(0, $line_items[0]->tax_rate1);
    }

    public function testCancelledInvoiceIsNotMutated(): void
    {
        $invoice = $this->buildInvoiceWithTaxableClient(Invoice::STATUS_CANCELLED);

        $invoice = $invoice->calc()->getInvoice();
        $line_items = $invoice->line_items;

        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals('', $line_items[0]->tax_name1);
        $this->assertEquals(0, $line_items[0]->tax_rate1);
    }

    public function testReversedInvoiceIsNotMutated(): void
    {
        $invoice = $this->buildInvoiceWithTaxableClient(Invoice::STATUS_REVERSED);

        $invoice = $invoice->calc()->getInvoice();
        $line_items = $invoice->line_items;

        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals('', $line_items[0]->tax_name1);
        $this->assertEquals(0, $line_items[0]->tax_rate1);
    }

    public function testDraftInvoiceMutationStillAllowed(): void
    {
        $invoice = $this->buildInvoiceWithTaxableClient(Invoice::STATUS_DRAFT);

        $invoice = $invoice->calc()->getInvoice();
        $line_items = $invoice->line_items;

        $this->assertEquals('Sales Tax', $line_items[0]->tax_name1);
        $this->assertEquals(8.75, $line_items[0]->tax_rate1);
    }

    public function testSentInvoiceMutationStillAllowed(): void
    {
        $invoice = $this->buildInvoiceWithTaxableClient(Invoice::STATUS_SENT);

        $invoice = $invoice->calc()->getInvoice();
        $line_items = $invoice->line_items;

        $this->assertEquals('Sales Tax', $line_items[0]->tax_name1);
        $this->assertEquals(8.75, $line_items[0]->tax_rate1);
    }

    public function testPartialInvoiceMutationStillAllowed(): void
    {
        $invoice = $this->buildInvoiceWithTaxableClient(Invoice::STATUS_PARTIAL);

        $invoice = $invoice->calc()->getInvoice();
        $line_items = $invoice->line_items;

        $this->assertEquals('Sales Tax', $line_items[0]->tax_name1);
        $this->assertEquals(8.75, $line_items[0]->tax_rate1);
    }

    public function testPaidInvoiceWithInclusiveTaxesIsNotMutated(): void
    {
        $invoice = $this->buildInvoiceWithTaxableClient(Invoice::STATUS_PAID, true);

        $invoice = $invoice->calc()->getInvoice();
        $line_items = $invoice->line_items;

        $this->assertEquals('', $line_items[0]->tax_name1);
        $this->assertEquals(0, $line_items[0]->tax_rate1);
    }
}
