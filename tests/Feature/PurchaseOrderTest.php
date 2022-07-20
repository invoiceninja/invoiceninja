<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }

    public function testPostNewPurchaseOrderPdf()
    {
        $purchase_order = [
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => Str::random(10),
            'po_number' => Str::random(5),
            'due_date' => '2022-01-01',
            'date' => '2022-01-01',
            'balance' => 100,
            'amount' => 100,
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'vendor_id' => $this->encodePrimaryKey($this->vendor->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/purchase_orders/', $purchase_order)
            ->assertStatus(200);

        $arr = $response->json();

        $purchase_order = PurchaseOrder::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertNotNull($purchase_order);

        $x = $purchase_order->service()->markSent()->getPurchaseOrderPdf();

        nlog($x);
    }

    public function testPurchaseOrderRest()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id).'/edit');

        $response->assertStatus(200);

        $purchase_order_update = [
            'tax_name1' => 'dippy',
        ];

        $this->assertNotNull($this->purchase_order);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id), $purchase_order_update)
            ->assertStatus(200);
    }

    public function testPostNewPurchaseOrder()
    {
        $purchase_order = [
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '34343xx43',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'vendor_id' => $this->encodePrimaryKey($this->vendor->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/purchase_orders/', $purchase_order)
            ->assertStatus(200);
    }

    public function testPurchaseOrderDelete()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id));

        $response->assertStatus(200);
    }

    public function testPurchaseOrderUpdate()
    {
        $data = [
            'status_id' => 1,
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'vendor_id' => $this->encodePrimaryKey($this->vendor->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/purchase_orders/'.$this->encodePrimaryKey($this->purchase_order->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/purchase_orders/', $data);

        $response->assertStatus(302);
    }
}
