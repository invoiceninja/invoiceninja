<?php

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
use Tests\MockAccountData;
use Tests\TestCase;

class ReverseTaxCalculationTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testDomesticReverseTaxIsDisplayedButNeverCharged(): void
    {
        $invoice = $this->makeInvoice('276', 276);
        $this->assertFalse(property_exists($invoice->line_items[0], 'reverse_tax_rate'));

        $calc = $invoice->calc();
        $calculated = $calc->getTempEntity();

        $this->assertTrue(property_exists($calculated->line_items[0], 'reverse_tax_rate'));
        $this->assertSame(0.0, (float) $calculated->line_items[0]->tax_rate1);
        $this->assertSame(0.0, (float) $calculated->line_items[0]->tax_amount);
        $this->assertSame(19.0, (float) $calculated->line_items[0]->reverse_tax_rate);
        $this->assertSame(100.0, (float) $calculated->amount);
        $this->assertSame(0.0, (float) $calculated->total_taxes);
        $this->assertCount(0, $calc->getTaxMap());
        $this->assertCount(1, $calc->getReverseTaxMap());
        $this->assertSame('Reverse Tax 19%', $calc->getReverseTaxMap()->first()['name']);
        $this->assertSame(19.0, (float) $calc->getReverseTaxMap()->first()['total']);
        $this->assertCount(1, $calc->getTaxMapForDisplay());
    }

    public function testDomesticReverseTaxUsesTheSameDisclosureForInclusiveInvoices(): void
    {
        $invoice = $this->makeInvoice('276', 276, true);
        $calc = $invoice->calc();
        $calculated = $calc->getTempEntity();

        $this->assertSame(100.0, (float) $calculated->amount);
        $this->assertSame(0.0, (float) $calculated->total_taxes);
        $this->assertSame(0.0, (float) $calculated->line_items[0]->tax_amount);
        $this->assertSame(19.0, (float) $calc->getReverseTaxMap()->first()['total']);
    }

    public function testAmountDiscountReducesOnlyTheDisplayedReverseTaxBase(): void
    {
        $invoice = $this->makeInvoice('276', 276);
        $invoice->discount = 10;
        $invoice->is_amount_discount = true;

        $calc = $invoice->calc();
        $calculated = $calc->getTempEntity();

        $this->assertSame(90.0, (float) $calculated->amount);
        $this->assertSame(0.0, (float) $calculated->total_taxes);
        $this->assertSame(17.1, (float) $calc->getReverseTaxMap()->first()['total']);
        $this->assertSame(90.0, (float) $calc->getReverseTaxMap()->first()['base_amount']);
    }

    public function testCrossBorderReverseTaxHasNoNotionalAmount(): void
    {
        $invoice = $this->makeInvoice('276', 250);
        $calc = $invoice->calc();
        $calculated = $calc->getTempEntity();

        $this->assertFalse(property_exists($calculated->line_items[0], 'reverse_tax_rate'));
        $this->assertSame(0.0, (float) $calculated->total_taxes);
        $this->assertCount(0, $calc->getReverseTaxMap());
    }

    public function testAustraliaDoesNotChargeOrDisplayReverseTax(): void
    {
        $invoice = $this->makeInvoice('036', 36);
        $calc = $invoice->calc();
        $calculated = $calc->getTempEntity();

        $this->assertSame(0.0, (float) $calculated->line_items[0]->tax_rate1);
        $this->assertSame(0.0, (float) $calculated->total_taxes);
        $this->assertSame(100.0, (float) $calculated->amount);
        $this->assertFalse(property_exists($calculated->line_items[0], 'reverse_tax_rate'));
        $this->assertCount(0, $calc->getReverseTaxMap());
    }

    public function testUnitedStatesReverseTaxDoesNotApplySalesTax(): void
    {
        $invoice = $this->makeInvoice('840', 840);
        $calc = $invoice->calc();
        $calculated = $calc->getTempEntity();

        $this->assertSame(0.0, (float) $calculated->line_items[0]->tax_rate1);
        $this->assertSame(0.0, (float) $calculated->total_taxes);
        $this->assertSame(100.0, (float) $calculated->amount);
        $this->assertCount(0, $calc->getReverseTaxMap());
    }

    public function testSupportedDomesticTerritoriesUseTheirStandardRate(): void
    {
        $cases = [
            ['826', 826, 20.0], // Great Britain / Northern Ireland
            ['826', 833, 20.0], // Isle of Man
            ['702', 702, 9.0],  // Singapore
            ['020', 20, 4.5],   // Andorra
        ];

        foreach ($cases as [$sellerCountryId, $buyerCountryId, $expectedRate]) {
            $calc = $this->makeInvoice($sellerCountryId, $buyerCountryId)->calc();
            $calculated = $calc->getTempEntity();

            $this->assertSame($expectedRate, (float) $calculated->line_items[0]->reverse_tax_rate);
            $this->assertSame($expectedRate, (float) $calc->getReverseTaxMap()->first()['total']);
            $this->assertSame(0.0, (float) $calculated->total_taxes);
        }
    }

    public function testIssuedDocumentKeepsItsStoredDisclosureRate(): void
    {
        $invoice = $this->makeInvoice('276', 276);
        $invoice = $invoice->calc()->getInvoice();
        $invoice->status_id = Invoice::STATUS_SENT;
        $invoice->saveQuietly();
        $invoice->company->tax_data->regions->EU->subregions->DE->tax_rate = 25;
        $invoice->company->saveQuietly();

        $calc = $invoice->fresh()->calc();
        $calculated = $calc->getTempEntity();

        $this->assertSame(19.0, (float) $calculated->line_items[0]->reverse_tax_rate);
        $this->assertSame(19.0, (float) $calc->getReverseTaxMap()->first()['total']);
    }

    private function makeInvoice(string $sellerCountryId, int $buyerCountryId, bool $inclusive = false)
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = $sellerCountryId;
        $settings->currency_id = '1';

        $taxData = new TaxModel();
        $sellerCode = match ($sellerCountryId) {
            '276' => 'DE',
            '036' => 'AU',
            '840' => 'CA',
            '826' => 'GB',
            '702' => 'SG',
            '020' => 'AD',
        };
        $taxData->seller_subregion = $sellerCode;
        $taxData->regions->EU->tax_all_subregions = true;
        $taxData->regions->AU->tax_all_subregions = true;
        $taxData->regions->US->tax_all_subregions = true;
        $taxData->regions->UK->tax_all_subregions = true;
        $taxData->regions->SG->tax_all_subregions = true;
        $taxData->regions->AD->tax_all_subregions = true;

        $usTaxData = new Response([
            'geoState' => 'CA',
            'taxSales' => 0.0875,
            'stateSalesTax' => 0.06,
            'txbService' => 'Y',
            'txbFreight' => 'Y',
            'originDestination' => 'D',
        ]);

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $taxData,
            'origin_tax_data' => $usTaxData,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => $buyerCountryId,
            'shipping_country_id' => $buyerCountryId,
            'state' => 'CA',
            'postal_code' => '90210',
            'tax_data' => $usTaxData,
            'is_tax_exempt' => false,
        ]);

        $item = new InvoiceItem();
        $item->quantity = 1;
        $item->cost = 100;
        $item->product_key = 'Reverse tax item';
        $item->tax_id = (string) Product::PRODUCT_TYPE_REVERSE_TAX;

        $invoice = InvoiceFactory::create($company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->uses_inclusive_taxes = $inclusive;
        $invoice->tax_data = $usTaxData;
        $invoice->line_items = [$item];

        return $invoice;
    }
}
