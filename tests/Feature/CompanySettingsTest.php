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

    public function testSettingCasts()
    {
        $settings = $this->company->settings;

        $settings->client_number_counter = "a";

        $this->company->settings = $settings;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-Token' => $this->token,
            ])->put('/api/v1/companies/'.$this->encodePrimaryKey($this->company->id), $this->company->toArray());


        $response->assertStatus(200);    

        $arr = $response->json();
        //\Log::error($arr);

       // $this->assertEquals($arr['data']['settings']['client_number_counter'],1);
    }
}