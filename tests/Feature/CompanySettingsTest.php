<?php

namespace Tests\Feature;

use App\DataMapper\DefaultSettings;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Faker\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Utils\Traits\CompanySettingsSaver
 */
class CompanySettingsTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;


    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

    }

    public function testClientNumberCantBeModified()
    {
        $settings = $this->company->settings;

        $settings->client_number_counter = 200;

        $this->company->settings = $settings;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());


        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals($arr['data']['settings']['timezone_id'],15);
    }

    public function testIntegerEdgeCases()
    {
        $settings = $this->company->settings;

        $settings->client_number_counter = "a";
        $settings->invoice_number_counter = 1000;
        $settings->quote_number_counter = 1.2;
        $settings->credit_number_counter = 10.1;

        $this->company->settings = $settings;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(302);    

        //$arr = $response->json();

/*
        $this->assertEquals($arr['data']['settings']['client_number_counter'],1);
        $this->assertEquals($arr['data']['settings']['quote_number_counter'],1);
        $this->assertEquals($arr['data']['settings']['credit_number_counter'],1);
        $this->assertEquals($arr['data']['settings']['invoice_number_counter'],1000);
*/
    }

    public function testFloatEdgeCases()
    {
        $settings = $this->company->settings;

        $settings->default_task_rate = "a";
        $settings->tax_rate1 = 10.0;
        $settings->tax_rate2 = "10.0";
        $settings->tax_rate3 = "10.5";

        $this->company->settings = $settings;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(302);    

       // $arr = $response->json();

        // $this->assertEquals($arr['data']['settings']['default_task_rate'],0);
        // $this->assertEquals($arr['data']['settings']['tax_rate1'],10.0);
        // $this->assertEquals($arr['data']['settings']['tax_rate2'],10.0);
        // $this->assertEquals($arr['data']['settings']['tax_rate3'],10.5);
    }

    public function testBoolEdgeCases()
    {
        $settings = $this->company->settings;

        $settings->require_invoice_signature = true;
        $settings->require_quote_signature = true;
        $settings->show_accept_quote_terms = false;
        $settings->show_accept_invoice_terms = "TRUE";
        $settings->show_tasks_in_portal = "FALSE";

        $this->company->settings = $settings;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());

        $response->assertStatus(302);    

     //   $arr = $response->json();

        // $this->assertEquals($arr['data']['settings']['require_invoice_signature'],1);
        // $this->assertEquals($arr['data']['settings']['require_quote_signature'],1);
        // $this->assertEquals($arr['data']['settings']['show_accept_quote_terms'],0);
        // $this->assertEquals($arr['data']['settings']['show_accept_invoice_terms'],1);
        // $this->assertEquals($arr['data']['settings']['show_tasks_in_portal'],0);
    }
    
}