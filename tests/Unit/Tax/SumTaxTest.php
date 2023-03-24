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

use Tests\TestCase;
use App\Models\Client;
use Tests\MockAccountData;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test 
 */
class SumTaxTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    
    public array $response = [
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


    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();
    }

    public function testTaxOnInvoice()
    {
        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $c->tax_data = $this->response;
        $c->save();

        $this->assertEquals("92582", $c->tax_data->geoPostalCode);
        $this->assertEquals(0.0875, $c->tax_data->taxSales);
    }

    public function testSumOfInvoice()
    {

        $this->assertEquals("CA", $this->response['geoState']);

    }

    public function testSumOfTaxes()
    {
        $sum = 
            $this->response['stateSalesTax'] +
            // $this->response['stateUseTax'] +
            $this->response['citySalesTax'] +
            // $this->response['cityUseTax'] +
            $this->response['countySalesTax'] +
            // $this->response['countyUseTax'] +
            $this->response['districtSalesTax'];
            // // $this->response['districtUseTax'] +
            // $this->response['district1SalesTax'] +
            // // $this->response['district1UseTax'] +
            // $this->response['district2SalesTax'] +
            // // $this->response['district2UseTax'] +
            // $this->response['district3SalesTax'] +
            // // $this->response['district3UseTax'] +
            // $this->response['district4SalesTax'] +
            // // $this->response['district4UseTax'] +
            // $this->response['district5SalesTax'];
            // $this->response['district5UseTax'];

        $this->assertEquals(0.0875, $sum);
    }

}