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
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\DataMapper\Tax\DE\Rule;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\ZipTax\Response;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test App\Services\Tax\Providers\EuTax
 */
class EuTaxTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    
    protected function setUp() :void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();
    }

    public function testInvoiceTaxCalcDetoBeNoVat()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany
        
        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_data' => new Response([]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(21, $invoice->line_items[0]->tax_rate1);
        $this->assertEquals(121, $invoice->amount);
    }

    public function testInvoiceTaxCalcDetoBe()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany
        
        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_data' => new Response([]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(0, $invoice->line_items[0]->tax_rate1);
        $this->assertEquals(100, $invoice->amount);
    }


    public function testInvoiceTaxCalcDetoDe()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany
        
        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_data' => new Response([]),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(19, $invoice->line_items[0]->tax_rate1);
        $this->assertEquals(119, $invoice->amount);

    }


    public function testCorrectRuleInit()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all = true;
        
        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => false,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertEquals('DE', $process->vendor_country_code);

        $this->assertEquals('DE', $process->client_country_code);

        $this->assertFalse($client->has_valid_vat_number);

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertEquals(19, $process->vat_rate);

        $this->assertEquals(7, $process->reduced_vat_rate);


    }
    
    public function testEuCorrectRuleInit()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => false,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();


        $this->assertEquals('DE', $process->vendor_country_code);

        $this->assertEquals('BE', $process->client_country_code);

        $this->assertFalse($client->has_valid_vat_number);

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertEquals(21, $process->vat_rate);

        $this->assertEquals(6, $process->reduced_vat_rate);


    }

    public function testForeignCorrectRuleInit()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 840,
            'shipping_country_id' => 840,
            'has_valid_vat_number' => false,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertEquals('DE', $process->vendor_country_code);

        $this->assertEquals('US', $process->client_country_code);

        $this->assertFalse($client->has_valid_vat_number);

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertEquals(0, $process->vat_rate);

        $this->assertEquals(0, $process->reduced_vat_rate);


    }

    public function testSubThresholdCorrectRate()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => false,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertFalse($client->has_valid_vat_number);

        $this->assertEquals(19, $process->vat_rate);

        $this->assertEquals(7, $process->reduced_vat_rate);

    }


    //tests with valid vat.
    public function testDeWithValidVat()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => true,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->has_valid_vat_number);

        $this->assertEquals(19, $process->vat_rate);

        $this->assertEquals(7, $process->reduced_vat_rate);

    }

 //tests with valid vat.
    public function testDeToEUWithValidVat()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => true,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->has_valid_vat_number);

        $this->assertEquals(0, $process->vat_rate);

        $this->assertEquals(0, $process->reduced_vat_rate);

    }

    public function testTaxExemptionDeSellerBeBuyer()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => true,
            'is_tax_exempt' => true,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->is_tax_exempt);

        $this->assertEquals(0, $process->vat_rate);

        $this->assertEquals(0, $process->reduced_vat_rate);

    }

    public function testTaxExemptionDeSellerDeBuyer()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => true,
            'is_tax_exempt' => true,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->is_tax_exempt);

        $this->assertEquals(0, $process->vat_rate);

        $this->assertEquals(0, $process->reduced_vat_rate);

    }

    public function testTaxExemption3()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_region = 'DE';
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 840,
            'shipping_country_id' => 840,
            'has_valid_vat_number' => true,
            'is_tax_exempt' => true,
        ]);

        $process = new Rule();
        $process->setClient($client);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->is_tax_exempt);

        $this->assertEquals(0, $process->vat_rate);

        $this->assertEquals(0, $process->reduced_vat_rate);

    }

}
