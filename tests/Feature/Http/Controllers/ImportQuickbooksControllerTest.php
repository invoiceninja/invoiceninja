<?php

namespace Tests\Feature\Http\Controllers;

use App\Services\Quickbooks\Contracts\SdkInterface as QuickbooksInterface;
use App\Services\Quickbooks\Service as QuickbooksService;
use App\Services\Quickbooks\SdkWrapper as QuickbooksSDK;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\MockAccountData;
use Tests\TestCase;
use Mockery;

class ImportQuickbooksControllerTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private $mock;
    private $state;

    protected function setUp(): void
    {

        parent::setUp();

        $this->markTestSkipped("no bueno");

        $this->state = Str::random(4);
        $this->mock = Mockery::mock(stdClass::class);
        $this->makeTestData();

        Session::start();

        //app()->singleton(QuickbooksInterface::class, fn() => new QuickbooksSDK($this->mock));
    }

    public function testAuthorize(): void
    {

        $this->mock->shouldReceive('getState')->andReturn($this->state);
        $this->mock->shouldReceive('getAuthorizationCodeURL')->andReturn('https://example.com');
        $this->mock->shouldReceive("getOAuth2LoginHelper")->andReturn($this->mock);

        Cache::spy();
        Cache::shouldReceive('get')
                    ->with($token  = $this->company->company_key)
                    ->andReturn(['company_key' => $token, 'id' => $this->company->id]);
        Cache::shouldReceive('has')
                    ->andReturn(true);
        // Perform the test
        $response = $this->get(route('authorize.quickbooks', ['token' => $token]));
        $response->assertStatus(302);

        Cache::shouldHaveReceived('put')->once()->with($this->state, $token, 90);
    }

    public function testOnAuthorized(): void
    {
        $token = ['company_key' => $this->company->company_key, 'id' => $this->company->id] ;

        $this->mock->shouldReceive('getAccessToken')->andReturn(Mockery::mock(stdClass::class, function (MockInterface $mock) {
            $mock->shouldReceive('getAccessToken')->andReturn('abcdefg');
            $mock->shouldReceive('getRefreshToken')->andReturn('abcdefghi');
            $mock->shouldReceive('getAccessTokenExpiresAt')->andReturn(3600);
            $mock->shouldReceive('getRefreshTokenExpiresAt')->andReturn(8726400);
        }));
        $this->mock->shouldReceive("getOAuth2LoginHelper")->andReturn($this->mock);
        $this->mock->shouldReceive('exchangeAuthorizationCodeForToken')->once();

        Cache::spy();
        Cache::shouldReceive('has')
                    ->andReturn(true);
        Cache::shouldReceive('get')->andReturn($token);
        Cache::shouldReceive('pull')->andReturn($token['company_key']);
        // Perform the test
        $response = $this->get("/quickbooks/authorized/?code=123456&state={$this->state}&realmId=12345678");
        $response->assertStatus(200);

        Cache::shouldHaveReceived('put')->once()->with($token['company_key'], 'abcdefg', 3600);

        $this->mock->shouldHaveReceived('exchangeAuthorizationCodeForToken')->once()->with(123456, 12345678);
    }

    public function testImport(): void
    {
        // Cache::spy();
        //Bus::fake();
        $data = $this->setUpTestData('customers');
        $count = count($data);
        $this->mock->shouldReceive('Query')->andReturnUsing(
            function ($val, $s = 1, $max = 1000) use ($count, $data) {
                if(stristr($val, 'count')) {
                    return $count;
                }

                return Arr::take($data, $max);
            }
        );

        // Perform the test
        $response = $this->actingAs($this->user)->withHeaders([
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/import/quickbooks', [
                'import_types' => ['client']
            ]);
        $response->assertStatus(200);

        //Cache::shouldHaveReceived('has')->once()->with("{$hash}-client");
        //Bus::assertDispatched(\App\Jobs\Import\QuickbooksIngest::class);
    }

    protected function setUpTestData($file)
    {
        $data = json_decode(
            file_get_contents(base_path("tests/Mock/Quickbooks/Data/$file.json")),
            true
        );

        return $data;
    }
}
