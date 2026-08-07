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
use App\Models\Credit;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Product;
use Tests\MockAccountData;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Guards the sign of the SERIALISED Storecove payload (the actual bytes sent to
 * the API via sendJsonDocument) for credit notes — asserting on the encoded
 * document, NOT on model getters (the serializer encodes raw property values).
 *
 * Storecove represents a credit as a NEGATIVE INVOICE:
 *   - line quantity POSITIVE
 *   - line price + line amounts NEGATIVE
 *   - tax subtotals + document total NEGATIVE
 *
 * Also proves the equivalence: a negative Invoice and a Credit produce the same
 * negative-invoice payload (both are routed through the Credit model).
 *
 * Pipeline mirrors production (SendEDocument::handle):
 *   Peppol($model)->run() → transformFromPeppol() → decorate() → getDocument()
 */
class CreditNoteSignConsistencyTest extends TestCase
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

        $this->setupCompany();
    }

    private function setupCompany(): void
    {
        $settings = CompanySettings::defaults();
        $settings->vat_number = 'DE923356489';
        $settings->id_number = '01234567890';
        $settings->classification = 'business';
        $settings->country_id = Country::where('iso_3166_2', 'DE')->first()->id;
        $settings->email = uniqid('testuser') . '@gmail.com';
        $settings->currency_id = '3';

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->seller_subregion = 'DE';

        $einvoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();
        $pm = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans();
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
    }

    private function createClient(): Client
    {
        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', 'DE')->first()->id,
            'vat_number' => 'DE173755434',
            'classification' => 'business',
            'has_valid_vat_number' => true,
            'name' => 'Test Client',
        ]);

        ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' => $client->company_id,
            'user_id' => $client->user_id,
            'email' => uniqid('testuser') . '@gmail.com',
        ]);

        return $client;
    }

    private function lineItem(float $cost, float $quantity = 1, float $discount = 0): InvoiceItem
    {
        $item = new InvoiceItem();
        $item->product_key = 'Widget';
        $item->notes = 'A nice widget';
        $item->quantity = $quantity;
        $item->cost = $cost;
        $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
        $item->tax_name1 = 'VAT';
        $item->tax_rate1 = 19;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;
        $item->discount = $discount;
        $item->is_amount_discount = false;
        $item->unit_code = 'C62';

        return $item;
    }

    /**
     * Storecove validates each line: itemPrice*quantity/baseQuantity +
     * allowanceCharges - amount must be within two cents of zero. Returns the
     * residual for the first line of the given wire document.
     */
    private function lineResidual(array $doc): float
    {
        $line = $doc['invoice_lines'][0];
        $baseQty = $line['base_quantity'] ?? 1.0;

        $allowance = 0.0;
        foreach ($line['allowance_charges'] ?? [] as $ac) {
            $allowance += $ac['amount_excluding_tax'] ?? 0.0;
        }

        return ($line['item_price'] * $line['quantity'] / $baseQty) + $allowance - $line['amount_excluding_vat'];
    }

    private function baseAttributes(Client $client, array $lineItems): array
    {
        return [
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => now()->addDay()->format('Y-m-d'),
            'due_date' => now()->addDays(2)->format('Y-m-d'),
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'is_amount_discount' => false,
            'line_items' => $lineItems,
            'tax_rate1' => 0, 'tax_name1' => '',
            'tax_rate2' => 0, 'tax_name2' => '',
            'tax_rate3' => 0, 'tax_name3' => '',
        ];
    }

    private function createInvoice(Client $client, array $lineItems): Invoice
    {
        return Invoice::factory()->create($this->baseAttributes($client, $lineItems))->calc()->getInvoice();
    }

    private function createCredit(Client $client, array $lineItems): Credit
    {
        return Credit::factory()->create($this->baseAttributes($client, $lineItems))->calc()->getCredit();
    }

    /**
     * Run the production pipeline and return the serialised Storecove document
     * array (what sendJsonDocument POSTs under payload['document']['invoice']).
     */
    private function wireDocument(Invoice|Credit $model): array
    {
        $peppol = (new Peppol($model))->run();

        $storecove = new Storecove();
        $storecove->adapter
            ->transformFromPeppol($model, $peppol->getDocument(), $peppol->isCreditNote())
            ->decorate();

        $result = $storecove->adapter->getDocument();

        $this->assertEmpty($result['errors'], 'Storecove transform reported errors: ' . json_encode($result['errors']));

        return $result['document'];
    }

    // ─────────────────────── Credit → negative invoice ───────────────────────

    public function testCreditSerialisesAsNegativeInvoiceWithPositiveQuantity(): void
    {
        $client = $this->createClient();
        $credit = $this->createCredit($client, [$this->lineItem(100.0, 2)]);

        $doc = $this->wireDocument($credit);
        $line = $doc['invoice_lines'][0];

        // quantity POSITIVE, price + amounts NEGATIVE
        $this->assertGreaterThan(0, $line['quantity'], 'CreditedQuantity must be positive');
        $this->assertLessThan(0, $line['item_price'], 'Unit price must be negative');
        $this->assertLessThan(0, $line['amount_excluding_vat'], 'LineExtensionAmount must be negative');
        $this->assertLessThan(0, $doc['amount_including_vat'], 'Document total must be negative');
        $this->assertLessThan(0, $doc['tax_subtotals'][0]['tax_amount'], 'Tax amount must be negative');
        $this->assertLessThan(0, $doc['tax_subtotals'][0]['taxable_amount'], 'Taxable amount must be negative');

        // Arithmetic coherence on the wire.
        $this->assertEqualsWithDelta(
            $line['item_price'] * $line['quantity'],
            $line['amount_excluding_vat'],
            0.01,
            'item_price × quantity must equal the line extension amount'
        );
    }

    /**
     * Reproduces the Storecove rejection: a DISCOUNTED credit line must
     * reconcile (itemPrice*qty + allowanceCharges - amount ≈ 0). This is the
     * case that failed in production with a 20% discount.
     */
    public function testDiscountedCreditLineReconciles(): void
    {
        $client = $this->createClient();
        $credit = $this->createCredit($client, [$this->lineItem(671.2, 1, 20)]);

        $doc = $this->wireDocument($credit);
        $line = $doc['invoice_lines'][0];

        // Sanity: a discount allowance is present on the line.
        $this->assertNotEmpty($line['allowance_charges'] ?? [], 'Expected a discount allowance on the line');

        $this->assertLessThanOrEqual(
            0.02,
            abs($this->lineResidual($doc)),
            'Storecove line equation must reconcile to within two cents. Residual: ' . $this->lineResidual($doc)
                . ' | line: ' . json_encode($line)
        );

        // Positive quantity, negative price + line amount.
        $this->assertGreaterThan(0, $line['quantity']);
        $this->assertLessThan(0, $line['item_price']);
        $this->assertLessThan(0, $line['amount_excluding_vat']);
    }

    /**
     * A discounted NEGATIVE INVOICE must reconcile identically.
     */
    public function testDiscountedNegativeInvoiceLineReconciles(): void
    {
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, [$this->lineItem(-671.2, 1, 20)]);

        $this->assertTrue($invoice->amount < 0, 'Fixture sanity: invoice amount should be negative');

        $doc = $this->wireDocument($invoice);

        $this->assertLessThanOrEqual(
            0.02,
            abs($this->lineResidual($doc)),
            'Storecove line equation must reconcile for a negative invoice. Residual: ' . $this->lineResidual($doc)
        );
    }

    /**
     * Control: a DISCOUNTED positive invoice must still reconcile (guards the
     * !$isCredit branch of the allowance flip).
     */
    public function testDiscountedPositiveInvoiceLineReconciles(): void
    {
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, [$this->lineItem(671.2, 1, 20)]);

        $doc = $this->wireDocument($invoice);

        $this->assertLessThanOrEqual(
            0.02,
            abs($this->lineResidual($doc)),
            'Storecove line equation must reconcile for a positive invoice. Residual: ' . $this->lineResidual($doc)
        );
    }

    // ───────────────────────────── Equivalence ───────────────────────────────

    /**
     * A negative Invoice and a Credit of equal magnitude produce an identical
     * negative-invoice payload — both are routed through the Credit model.
     */
    public function testNegativeInvoiceAndCreditProduceIdenticalPayload(): void
    {
        $client = $this->createClient();

        $credit = $this->createCredit($client, [$this->lineItem(100.0, 2)]);
        $negativeInvoice = $this->createInvoice($client, [$this->lineItem(-100.0, 2)]);

        $this->assertTrue($negativeInvoice->amount < 0, 'Fixture sanity: invoice amount should be negative');

        $creditLine = $this->wireDocument($credit)['invoice_lines'][0];
        $negInvoiceLine = $this->wireDocument($negativeInvoice)['invoice_lines'][0];

        $this->assertEqualsWithDelta($creditLine['quantity'], $negInvoiceLine['quantity'], 0.01);
        $this->assertEqualsWithDelta($creditLine['item_price'], $negInvoiceLine['item_price'], 0.01);
        $this->assertEqualsWithDelta($creditLine['amount_excluding_vat'], $negInvoiceLine['amount_excluding_vat'], 0.01);
    }

    // ────────────────────────────── Control ──────────────────────────────────

    public function testPositiveInvoiceStaysPositive(): void
    {
        $client = $this->createClient();
        $invoice = $this->createInvoice($client, [$this->lineItem(100.0, 2)]);

        $doc = $this->wireDocument($invoice);
        $line = $doc['invoice_lines'][0];

        $this->assertGreaterThan(0, $line['quantity']);
        $this->assertGreaterThan(0, $line['item_price']);
        $this->assertGreaterThan(0, $line['amount_excluding_vat']);
        $this->assertGreaterThan(0, $doc['amount_including_vat']);
    }
}
