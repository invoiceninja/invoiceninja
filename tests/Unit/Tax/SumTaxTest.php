<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit\Tax;

use App\DataMapper\CompanySettings;
use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\TaxData;
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
 * 
 */
class SumTaxTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public Response $response;

    public array $resp = [
            "geoPostalCode" => "92582",
            "geoCity" => "SAN JACINTO",
            "geoCounty" => "RIVERSIDE",
            "geoState" => "CA",
            "taxSales" => 0.0875,
            "taxUse" => 0.0875, // tax amount where destination does not charge sales tax, but origin does
            "txbService" => "N", // whether services are taxed in this locale
            "txbFreight" => "N", // whether freight is taxes in this locale
            "stateSalesTax" => 0.06,
            "stateUseTax" => 0.06,
            "citySalesTax" => 0.01,
            "cityUseTax" => 0.01,
            "cityTaxCode" => "874",
            "countySalesTax" => 0.0025,
            "countyUseTax" => 0.0025,
            "countyTaxCode" => "",
            "districtSalesTax" => 0.015,
            "districtUseTax" => 0.015,
            "district1Code" => "26",
            "district1SalesTax" => 0,
            "district1UseTax" => 0,
            "district2Code" => "26",
            "district2SalesTax" => 0.005,
            "district2UseTax" => 0.005,
            "district3Code" => "",
            "district3SalesTax" => 0,
            "district3UseTax" => 0,
            "district4Code" => "33",
            "district4SalesTax" => 0.01,
            "district4UseTax" => 0.01,
            "district5Code" => "",
            "district5SalesTax" => 0,
            "district5UseTax" => 0, //district1-5 portion of the district tax
            "originDestination" => "D", //location where this is taxed origin/destination/null
        ];


    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();

        $this->response = new Response($this->resp);

    }

    /** Proves that we do not charge taxes automatically */
    public function testCalcInvoiceNoTax()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '840'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'CA';
        $tax_data->regions->US->has_sales_above_threshold = true;
        $tax_data->regions->US->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => false,
            'origin_tax_data' => new Response($this->resp),
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 840,
            'state' => 'CA',
            'postal_code' => '90210',
            'tax_data' => new Response($this->resp),
        ]);

        $invoice = InvoiceFactory::create($company->id, $this->user->id);
        $invoice->client_id = $client->id;
        $invoice->uses_inclusive_taxes = false;

        $line_items = [];

        $invoice->tax_data = $tax_data;

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 10;
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->tax_id = Product::PRODUCT_TYPE_PHYSICAL;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $line_items = $invoice->line_items;

        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals("", $line_items[0]->tax_name1);
        $this->assertEquals(0, $line_items[0]->tax_rate1);
    }

    /** Proves that we do calc taxes automatically */
    public function testCalcInvoiceTax()
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
        $invoice->uses_inclusive_taxes = false;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 10;
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->tax_id = Product::PRODUCT_TYPE_PHYSICAL;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $line_items = $invoice->line_items;

        $this->assertEquals(10.88, $invoice->amount);
        $this->assertEquals("Sales Tax", $line_items[0]->tax_name1);
        $this->assertEquals(8.75, $line_items[0]->tax_rate1);
    }

    public function testTaxOnCompany()
    {

        $tax_class = new TaxData($this->response);

        $this->company->tax_data = $tax_class;
        $this->company->save();

        $this->assertEquals("92582", $this->company->tax_data->origin->geoPostalCode);
        $this->assertEquals(0.0875, $this->company->tax_data->origin->taxSales);

    }

    public function testTaxOnClient()
    {
        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $tax_class = new TaxData($this->response, $this->response);

        $c->tax_data = $tax_class;
        $c->save();

        $this->assertEquals("92582", $c->tax_data->origin->geoPostalCode);
        $this->assertEquals(0.0875, $c->tax_data->origin->taxSales);

    }

    public function testTaxOnInvoice()
    {

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
        ]);

        $tax_class = new TaxData($this->response);

        $i->tax_data = $tax_class;
        $i->save();


        $this->assertEquals("92582", $i->tax_data->origin->geoPostalCode);
        $this->assertEquals(0.0875, $i->tax_data->origin->taxSales);


    }

    public function testSumOfInvoice()
    {

        $this->assertEquals("CA", $this->response->geoState);

    }

    public function testSumOfTaxes()
    {
        $sum =
            $this->response->stateSalesTax +
            $this->response->citySalesTax +
            $this->response->countySalesTax +
            $this->response->districtSalesTax;

        $this->assertEquals(0.0875, $sum);
    }

}
