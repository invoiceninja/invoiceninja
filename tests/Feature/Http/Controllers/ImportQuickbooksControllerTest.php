<?php

namespace Tests\Feature\Http\Controllers;

use App\Services\Import\Quickbooks\Contracts\SdkInterface as QuickbooksInterface;
use App\Services\Import\Quickbooks\Service as QuickbooksService;
use App\Services\Import\Quickbooks\SdkWrapper as QuickbooksSDK;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use GuzzleHttp\Psr7\Message;
use Illuminate\Support\Arr;
use Tests\MockAccountData;
use Tests\TestCase;
use Mockery;

class ImportQuickbooksControllerTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    
    protected function setUp(): void {

        parent::setUp();
        
        $this->makeTestData();
    
        Session::start();
    }

    public function testPreImportQuickbooksController(): void
    {
        Cache::spy();

        $data = $this->setUpTestData('customers');
        // Perform the test
        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/import/quickbooks/preimport',[
        'import_type' => 'client'
        ]);
        
        $response->assertStatus(200);
        $response = json_decode( $response->getContent());

        $this->assertNotNull($response->hash);
        Cache::shouldHaveReceived('put')->once();
    }

    public function testImportQuickbooksCustomers(): void
    {
        Cache::spy();
        Bus::fake();

        $this->setUpTestData('customers');
        // Perform the test
        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/import/quickbooks/preimport',[
        'import_type' => 'client'
        ]);
        $response->assertStatus(200);
        $response = json_decode( $response->getContent());
        $this->assertNotNull($response->hash);
        $hash = $response->hash;
        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/import/quickbooks',[
            'import_type' => 'client',
            'hash' => $response->hash
            ]);

        $response->assertStatus(200);
        Cache::shouldHaveReceived('has')->once()->with("{$hash}-client");
        Bus::assertDispatched(\App\Jobs\Import\QuickbooksIngest::class);
    }

    protected function setUpTestData($file) {
        $data = json_decode(
            file_get_contents(base_path("tests/Mock/Quickbooks/Data/$file.json")),true
        );
        $count = count($data);
        $sdkMock = Mockery::mock(sdtClass::class);
        $sdkMock->shouldReceive('Query')->andReturnUsing(function($val, $s = 1, $max = 1000) use ($count, $data) {
            if(stristr($val, 'count')) {
                return $count;
            }

            return Arr::take($data,$max);
        });
        app()->singleton(QuickbooksInterface::class, fn() => new QuickbooksSDK($sdkMock));

        return $data;
    }
}
