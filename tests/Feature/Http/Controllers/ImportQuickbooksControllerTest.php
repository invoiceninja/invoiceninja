<?php

namespace Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Bus;
use GuzzleHttp\Psr7\Message;
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

        $data = ($this->setUpTestResponseData('200-cutomer-response.txt'))['QueryResponse']['Customer'];
        // Create a mock of the UserController
        $controllerMock = Mockery::mock('App\Http\Controllers\ImportQuickbooksController[getData]')->shouldAllowMockingProtectedMethods();
        // Define what the mocked getData method should return
        $controllerMock->shouldReceive('getData')
            ->once()
            ->andReturn( $data, true);
        // Bind the mock to the Laravel container
        $this->app->instance('App\Http\Controllers\ImportQuickbooksController', $controllerMock);
        // Perform the test
        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/import/quickbooks/preimport',[
        'import_type' => 'client'
        ]);
        $response->assertStatus(200);
        $response = json_decode( $response->getContent());
        $this->assertNotNull($response->hash);

        Cache::shouldHaveReceived('put')->once()->with("{$response->hash}-client", base64_encode(json_encode($data)),600);
    }

    public function testImportQuickbooksCustomers(): void
    {

        Bus::fake();

        $data = ($this->setUpTestResponseData('200-cutomer-response.txt'))['QueryResponse']['Customer'];
        // Create a mock of the UserController
        $controllerMock = Mockery::mock('App\Http\Controllers\ImportQuickbooksController[getData]')->shouldAllowMockingProtectedMethods();
        // Define what the mocked getData method should return
        $controllerMock->shouldReceive('getData')
            ->once()
            ->andReturn( $data, true);
        // Bind the mock to the Laravel container
        $this->app->instance('App\Http\Controllers\ImportQuickbooksController', $controllerMock);
        // Perform the test
        $response = $this->withHeaders([
        'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/import/quickbooks/preimport',[
        'import_type' => 'client'
        ]);
        $response->assertStatus(200);
        $response = json_decode( $response->getContent());
        $this->assertNotNull($response->hash);
        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/import/quickbooks',[
            'import_type' => 'client',
            'hash' => $response->hash
            ]);
        $response->assertStatus(200);
        
        Bus::assertDispatched(\App\Jobs\Import\QuickbooksIngest::class);
    }

    protected function setUpTestResponseData($file) {
        $fullResponse = file_get_contents( base_path("tests/Mock/Quickbooks/Http/Response/$file") );
        // Parse the full response using Guzzle
        $response = Message::parseResponse($fullResponse);
        // Extract the JSON body
        $jsonBody = (string) $response->getBody();
        // Decode the JSON body to an array
        return json_decode($jsonBody, true);
    }
}
