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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Http\Controllers\DocumentController
 */
class DocumentsApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testClientDocuments()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertArrayHasKey('documents', $arr['data'][0]);
    }

    public function testInvoiceDocuments()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertArrayHasKey('documents', $arr['data'][0]);
    }

    public function testProjectsDocuments()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/projects');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertArrayHasKey('documents', $arr['data'][0]);
    }

    public function testExpenseDocuments()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertArrayHasKey('documents', $arr['data'][0]);
    }

    public function testVendorDocuments()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/vendors');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertArrayHasKey('documents', $arr['data'][0]);
    }

    public function testProductDocuments()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/products');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertArrayHasKey('documents', $arr['data'][0]);
    }

    public function testTaskDocuments()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/tasks');

        $response->assertStatus(200);
        $arr = $response->json();
        $this->assertArrayHasKey('documents', $arr['data'][0]);
    }
}
