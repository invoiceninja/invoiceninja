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

namespace Tests\Unit;

use App\DataMapper\ClientSettings;
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class SurchargeTaxTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testLineItemTaxHarvestUsesFirstLineOnly(): void
    {
        $invoice = $this->buildExclusiveInvoice(
            lineItems: [
                $this->line('A', 100, 1, 'GST', 10),
                $this->line('B', 100, 1, 'VAT', 20),
            ],
            surcharges: ['custom_surcharge1' => 50, 'custom_surcharge_tax1' => true],
        );

        $calc = $invoice->calc();

        // 100 + 100 + 50 surcharge = 250 net; 10% on lines (10+20) + 10% on surcharge (5) = 35
        $this->assertEqualsWithDelta(35.0, $calc->getTotalTaxes(), 0.01);
    }

    public function testInvoiceHeaderTaxesApplyToAllNamedRates(): void
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->tax_name1 = 'GST';
        $invoice->tax_rate1 = 10;
        $invoice->tax_name2 = 'PST';
        $invoice->tax_rate2 = 5;
        $invoice->custom_surcharge1 = 100;
        $invoice->custom_surcharge_tax1 = true;
        $invoice->line_items = [$this->line('A', 100, 1, '', 0)];
        $invoice->save();

        $calc = $invoice->calc();

        // Line 100 @ 10% = 10, line 100 @ 5% = 5, surcharge 100 @ 10% = 10, surcharge 100 @ 5% = 5
        $this->assertEqualsWithDelta(30.0, $calc->getTotalTaxes(), 0.01);
    }

    public function testNonPeppolRespectsPerSurchargeTaxFlags(): void
    {
        $invoice = $this->buildExclusiveInvoice(
            lineItems: [$this->line('A', 100, 1, 'GST', 10)],
            surcharges: [
                'custom_surcharge1' => 10,
                'custom_surcharge_tax1' => true,
                'custom_surcharge2' => 20,
                'custom_surcharge_tax2' => false,
            ],
        );

        $calc = $invoice->calc();

        // Line 10 + surcharge 10 only (not 20) @ 10%
        $this->assertEqualsWithDelta(11.0, $calc->getTotalTaxes(), 0.01);
        $this->assertEqualsWithDelta(141.0, $calc->getInvoice()->amount, 0.01);
    }

    public function testPeppolTaxesAllSurchargesRegardlessOfFlags(): void
    {
        $client = $this->peppolClient();

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->custom_surcharge1 = 10;
        $invoice->custom_surcharge_tax1 = false;
        $invoice->custom_surcharge2 = 20;
        $invoice->custom_surcharge_tax2 = false;
        $invoice->line_items = [$this->line('A', 100, 1, 'GST', 10)];
        $invoice->save();

        $calc = $invoice->calc();

        // Line 10 + (10+20) surcharges @ 10% = 13
        $this->assertEqualsWithDelta(13.0, $calc->getTotalTaxes(), 0.01);
        $this->assertEqualsWithDelta(143.0, $calc->getInvoice()->amount, 0.01);
    }

    public function testInclusiveLineTaxSurchargeUsesFirstLineHarvest(): void
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->discount = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->custom_surcharge1 = 100;
        $invoice->custom_surcharge_tax1 = true;
        $invoice->line_items = [
            $this->line('A', 110, 1, 'GST', 10),
            $this->line('B', 120, 1, 'VAT', 20),
        ];
        $invoice->save();

        $calc = $invoice->calc();

        // Lines 10 + 20; surcharge taxed at first-line GST 10% only (9.09) — not VAT 20%
        $this->assertEqualsWithDelta(39.09, $calc->getTotalTaxes(), 0.02);
    }

    public function testFirstLineOrderDeterminesInclusiveSurchargeRate(): void
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->discount = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->custom_surcharge1 = 100;
        $invoice->custom_surcharge_tax1 = true;
        $invoice->line_items = [
            $this->line('A', 120, 1, 'VAT', 20),
            $this->line('B', 110, 1, 'GST', 10),
        ];
        $invoice->save();

        $calc = $invoice->calc();

        // Surcharge uses first line VAT 20% (16.67), not second line GST 10%
        $this->assertEqualsWithDelta(46.67, $calc->getTotalTaxes(), 0.02);
    }

    public function testExclusiveFlatAmountNegativeLineDiscountIncreasesMagnitude(): void
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->is_amount_discount = true;
        $invoice->line_items = [
            $this->line('Flat', 100, -2, 'VAT', 10, discount: 20, isAmountDiscount: true),
        ];
        $invoice->save();

        $calc = $invoice->calc();
        $item = $calc->getInvoice()->line_items[0];

        $this->assertEqualsWithDelta(-220.0, $item->line_total, 0.01, 'Flat discount subtracts from signed line total');
        $this->assertEqualsWithDelta(-22.0, $calc->getTotalTaxes(), 0.01);
    }

    /**
     * @param  array<int, InvoiceItem>  $lineItems
     * @param  array<string, mixed>  $surcharges
     */
    private function buildExclusiveInvoice(array $lineItems, array $surcharges = []): \App\Models\Invoice
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name3 = '';
        $invoice->tax_rate3 = 0;
        $invoice->line_items = $lineItems;

        foreach ($surcharges as $key => $value) {
            $invoice->{$key} = $value;
        }

        $invoice->save();

        return $invoice;
    }

    private function peppolClient(): Client
    {
        $settings = ClientSettings::defaults();
        $settings->e_invoice_type = 'PEPPOL';

        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'settings' => $settings,
        ]);

        return $client;
    }

    private function line(
        string $key,
        float $cost,
        float $qty,
        string $taxName,
        float $taxRate,
        float $discount = 0,
        bool $isAmountDiscount = false,
    ): InvoiceItem {
        $item = new InvoiceItem();
        $item->product_key = $key;
        $item->notes = $key;
        $item->cost = $cost;
        $item->quantity = $qty;
        $item->discount = $discount;
        $item->is_amount_discount = $isAmountDiscount;
        $item->tax_id = 1;
        $item->tax_name1 = $taxName;
        $item->tax_rate1 = $taxRate;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;

        return $item;
    }
}
