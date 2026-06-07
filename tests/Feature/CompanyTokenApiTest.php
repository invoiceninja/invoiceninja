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

    public function testCompanyTokenPutWithTokenFieldInBody()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        // Real clients PUT the full token resource. The body contains a
        // `token` field (the raw token string) which collides with the
        // {token} route parameter via Request::__get's input-first lookup.
        $data = [
            'id' => $this->encodePrimaryKey($company_token->id),
            'name' => 'newname-with-collision',
            'token' => \Illuminate\Support\Str::random(64),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id), $data);

        $response->assertStatus(200);
        $this->assertEquals('newname-with-collision', $response->json('data.name'));
    }

    public function testCompanyTokenGetWithTokenInQueryString()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        // Query-string `?token=...` is merged into $this->all() and would
        // shadow the bound route param the same way a body field does.
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id).'?token=shadow-value');

        $response->assertStatus(200);
    }

    public function testCompanyTokenEditWithTokenInQueryString()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = CompanyToken::whereCompanyId($this->company->id)->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id).'/edit?token=shadow-value');

        $response->assertStatus(200);
    }

    public function testCompanyTokenDeleteWithTokenFieldInBody()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'delete-collision-' . uniqid();
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = false;
        $company_token->save();

        $data = [
            'token' => \Illuminate\Support\Str::random(64),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->deleteJson('/api/v1/tokens/'.$this->encodePrimaryKey($company_token->id), $data);

        $response->assertStatus(200);
    }

    public function testIsSystemFilter()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $systemToken = new CompanyToken();
        $systemToken->user_id = $this->user->id;
        $systemToken->company_id = $this->company->id;
        $systemToken->account_id = $this->account->id;
        $systemToken->name = 'system_token_' . uniqid();
        $systemToken->token = \Illuminate\Support\Str::random(64);
        $systemToken->is_system = true;
        $systemToken->save();

        $userToken = new CompanyToken();
        $userToken->user_id = $this->user->id;
        $userToken->company_id = $this->company->id;
        $userToken->account_id = $this->account->id;
        $userToken->name = 'user_token_' . uniqid();
        $userToken->token = \Illuminate\Support\Str::random(64);
        $userToken->is_system = false;
        $userToken->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens?is_system=true&per_page=500')->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($systemToken->hashed_id, $ids);
        $this->assertNotContains($userToken->hashed_id, $ids);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tokens?is_system=false&per_page=500')->assertStatus(200);

        $ids = array_column($response->json('data'), 'id');
        $this->assertContains($userToken->hashed_id, $ids);
        $this->assertNotContains($systemToken->hashed_id, $ids);
    }
}
