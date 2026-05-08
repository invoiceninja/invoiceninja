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
use App\Factory\InvoiceFactory;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Regression coverage for the rounding contract that the InvoiceNinja
 * invoice document and the PEPPOL XML document must both honor:
 *
 *   subtotal                = Σ line_total                       (each line 2dp)
 *   tax_map[c].total        = round(Σ line_net_in_c × rate, 2)   (per category, 2dp)
 *   total_taxes             = Σ tax_map[c].total                 (sum of 2dp values)
 *   amount                  = subtotal + total_taxes             (2dp by construction)
 *
 * PEPPOL BR-CO-15 demands TaxTotal/TaxAmount (BT-110) == Σ TaxSubtotal/TaxAmount (BT-117).
 * If we let `calcAmountLineTax` return unrounded floats (the f882d0c0c2 PEPPOL carve-out)
 * AND don't round at the category boundary in InvoiceSum::setTaxMap(), the two documents
 * disagree by 1c whenever multiple VAT categories produce fractional cents.
 *
 *   App\Helpers\Invoice\InvoiceSum
 *   App\Helpers\Invoice\InvoiceItemSum
 */
class PeppolRoundingTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->makeTestData();
    }

    private function buildClient(string $eInvoiceType): Client
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // DE
        $settings->currency_id = '3';  // EUR
        $settings->e_invoice_type = $eInvoiceType;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'calculate_taxes' => false,
        ]);

        return Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
        ]);
    }

    private function makeItem(float $cost, float $qty, float $rate, string $name = 'VAT'): InvoiceItem
    {
        $item = new InvoiceItem();
        $item->quantity = $qty;
        $item->cost = $cost;
        $item->tax_rate1 = $rate;
        $item->tax_name1 = $name;
        $item->product_key = 'Test';
        $item->notes = 'Test';
        $item->is_amount_discount = false;
        return $item;
    }

    private function buildInvoice(Client $client, array $items)
    {
        $invoice = InvoiceFactory::create($client->company_id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_name2 = '';
        $invoice->tax_name3 = '';
        $invoice->line_items = $items;
        $invoice->save();

        return $invoice->calc()->getInvoice();
    }

    /**
     * Two VAT categories whose unrounded per-line taxes sum to a value
     * that rounds DIFFERENTLY than the sum of per-category-rounded taxes.
     *
     *   Cat A: 33.33 × 19% = 6.3327
     *   Cat B: 33.33 ×  7% = 2.3331
     *   sum unrounded     = 8.6658  → naive round = 8.67
     *   sum of rounded    = 6.33 + 2.33 = 8.66    ← what PEPPOL emits
     *
     * Pre-fix: total_taxes = 8.6658 → invoice.amount = 75.33,
     *          but PEPPOL Σ BT-117 = 8.66 → BT-110 mismatch (BR-CO-15 fail).
     * Post-fix: total_taxes = 8.66 → invoice.amount = 75.32, matches PEPPOL.
     */
    public function testPeppolMultiCategoryRoundingMatches()
    {
        $client = $this->buildClient('PEPPOL');

        $invoice = $this->buildInvoice($client, [
            $this->makeItem(33.33, 1, 19, 'VAT19'),
            $this->makeItem(33.33, 1, 7, 'VAT7'),
        ]);

        $calc = $invoice->calc();
        $tax_map = $calc->getTaxMap();

        // Each category total has at most 2dp.
        foreach ($tax_map as $row) {
            $this->assertSame(
                round($row['total'], 2),
                (float) $row['total'],
                'tax_map[*].total must be rounded to 2dp'
            );
        }

        // BR-CO-15 analogue: invoice total_taxes == Σ category totals.
        $sum_categories = array_sum(array_map(fn ($r) => $r['total'], $tax_map->toArray()));
        $this->assertEqualsWithDelta(
            (float) $invoice->total_taxes,
            $sum_categories,
            0.0001,
            'invoice.total_taxes must equal the sum of per-category tax totals'
        );

        // amount == subtotal + total_taxes, all reconciled at 2dp.
        $this->assertEqualsWithDelta(
            round($calc->getSubTotal() + (float) $invoice->total_taxes, 2),
            (float) $invoice->amount,
            0.0001,
            'invoice.amount must equal subtotal + total_taxes'
        );

        // The arithmetic above pins the expected values precisely.
        $this->assertEquals(66.66, $calc->getSubTotal());
        $this->assertEquals(8.66, (float) $invoice->total_taxes);
        $this->assertEquals(75.32, (float) $invoice->amount);
    }

    /**
     * Single category, three lines whose unrounded sum has fractional cents.
     * Confirms that the per-category round at the boundary matches what the
     * PEPPOL serializer emits and what the invoice persists.
     */
    public function testPeppolSingleCategoryFractionalCents()
    {
        $client = $this->buildClient('PEPPOL');

        $invoice = $this->buildInvoice($client, [
            $this->makeItem(33.33, 1, 19),
            $this->makeItem(33.33, 1, 19),
            $this->makeItem(33.33, 1, 19),
        ]);

        // Per-line unrounded tax: 6.3327 × 3 = 18.9981 → rounds to 19.00.
        $this->assertEquals(99.99, $invoice->calc()->getSubTotal());
        $this->assertEquals(19.00, (float) $invoice->total_taxes);
        $this->assertEquals(118.99, (float) $invoice->amount);
    }

    /**
     * The non-PEPPOL path keeps its original behavior (per-line rounding by
     * calcAmountLineTax). The boundary round in setTaxMap is a no-op for
     * already-2dp inputs, so totals must be unchanged from pre-fix behavior.
     */
    public function testNonPeppolBehaviorUnchanged()
    {
        $client = $this->buildClient('EN16931'); // anything != 'PEPPOL'

        $invoice = $this->buildInvoice($client, [
            $this->makeItem(33.33, 1, 19, 'VAT19'),
            $this->makeItem(33.33, 1, 7, 'VAT7'),
        ]);

        // Pre-fix non-PEPPOL: each line tax already rounded — sum is already 2dp.
        // Cat A: round(33.33 * 0.19, 2) = 6.33
        // Cat B: round(33.33 * 0.07, 2) = 2.33
        $this->assertEquals(66.66, $invoice->calc()->getSubTotal());
        $this->assertEquals(8.66, (float) $invoice->total_taxes);
        $this->assertEquals(75.32, (float) $invoice->amount);
    }

    /**
     * Per-item display fields (tax_amount, gross_line_total) must be 2dp
     * even on PEPPOL clients — they're for UI and must never carry the
     * unrounded float that lives only in tax_collection.
     */
    public function testPerItemDisplayFieldsAreRounded()
    {
        $client = $this->buildClient('PEPPOL');

        $invoice = $this->buildInvoice($client, [
            $this->makeItem(33.33, 1, 19),
        ]);

        $line = $invoice->line_items[0];

        $this->assertSame(
            round($line->tax_amount, 2),
            (float) $line->tax_amount,
            'item.tax_amount must be 2dp'
        );
        $this->assertSame(
            round($line->gross_line_total, 2),
            (float) $line->gross_line_total,
            'item.gross_line_total must be 2dp'
        );
    }
}
