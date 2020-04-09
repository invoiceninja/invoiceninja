<?php

namespace Tests\Feature;

use App\DataMapper\DefaultSettings;
use App\Models\Account;
use App\Models\CompanyToken;
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
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 * @covers App\Http\Controllers\TokenController
 */
class CompanyTokenApiTest extends TestCase
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

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function testCompanyTokenList()
    {

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token
        ])->get('/api/v1/tokens');


        $response->assertStatus(200);
    }

    public function testCompanyTokenPost()
    {
        $data = [
            'name' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->post('/api/v1/tokens', $data);


        $response->assertStatus(200);
    }

    public function testCompanyTokenPut()
    {
        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $data = [
            'name' => "newname",
        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->put('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id), $data);


        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('newname', $arr['data']['name']);

    }

    public function testCompanyTokenGet()
    {

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id));


        $response->assertStatus(200);
    }

    public function testCompanyTokenNotArchived()
    {
        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token
            ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

}
