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

namespace Tests\Feature;

use App\Http\Middleware\PasswordProtection;
use App\Models\CompanyToken;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *
 *  App\Http\Controllers\TokenController
 */
class CompanyTokenApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();
        Model::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class,
        );
    }

    public function testCompanyTokenListFilter()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/tokens?filter=xx');

        $response->assertStatus(200);
    }

    public function testCompanyTokenList()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/tokens');

        $response->assertStatus(200);
    }

    public function testCompanyTokenPost()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/tokens', $data);

        $response->assertStatus(200);
    }

    public function testCompanyTokenPut()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $data = [
            'name' => 'newname',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id), $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals('newname', $arr['data']['name']);
    }

    public function testCompanyTokenGet()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id));

        $response->assertStatus(200);
    }

    public function testCompanyTokenNotArchived()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testCompanyTokenBulkArchive()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->where('is_system', false)->first();

        if (! $company_token) {
            $company_token = new CompanyToken();
            $company_token->user_id = $this->user->id;
            $company_token->company_id = $this->company->id;
            $company_token->account_id = $this->account->id;
            $company_token->name = 'bulk test token';
            $company_token->token = \Illuminate\Support\Str::random(64);
            $company_token->is_system = false;
            $company_token->save();
        }

        $data = [
            'ids' => [$this->encodePrimaryKey($company_token->id)],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tokens/bulk', $data);

        $arr = $response->json();
        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testCompanyTokenBulkRestore()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'restore test token';
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = false;
        $company_token->save();

        // Archive first
        $company_token->delete();

        $data = [
            'ids' => [$this->encodePrimaryKey($company_token->id)],
            'action' => 'restore',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tokens/bulk', $data);

        $arr = $response->json();
        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testCompanyTokenBulkDelete()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'delete test token';
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = false;
        $company_token->save();

        $data = [
            'ids' => [$this->encodePrimaryKey($company_token->id)],
            'action' => 'delete',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/tokens/bulk', $data);

        $arr = $response->json();
        $this->assertTrue($arr['data'][0]['is_deleted']);
    }
}
