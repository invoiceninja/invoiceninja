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

use Tests\TestCase;
use App\Utils\Ninja;
use App\Models\Activity;
use App\Helpers\Cache\Atomic;
use App\DataMapper\ClientSettings;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Models\PurchaseOrderInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Repositories\ActivityRepository;
use App\Events\PurchaseOrder\PurchaseOrderWasCreated;
use App\Events\PurchaseOrder\PurchaseOrderWasUpdated;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PurchaseOrderTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    public function testExpensePurchaseOrderConversion()
    {

        $p = PurchaseOrder::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
            'project_id' => $this->project->id,
        ]);

        $expense = $p->service()->expense();

        $this->assertEquals($expense->project_id, $this->project->id);
        $this->assertEquals($expense->client_id, $p->project->client_id);

    }

    public function testCloneInvoiceToPurchaseOrder()
    {
        $purchase_order_design_id = $this->encodePrimaryKey(7);
        $client_settings = ClientSettings::defaults();
        $client_settings->purchase_order_design_id = $purchase_order_design_id;
        $this->client->settings = $client_settings;
        $this->client->save();

        $line_items = $this->invoice->line_items;
        $line_items[0]->cost = 125.50;
        $line_items[0]->product_cost = 47.25;
        $this->invoice->line_items = $line_items;
        $this->invoice->design_id = 5;
        $this->invoice->save();
        $this->invoice->unsetRelation('client');

        $purchase_order_count = PurchaseOrder::count();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/bulk', [
            'action' => 'clone_to_purchase_order',
            'ids' => [$this->invoice->hashed_id],
        ])->assertStatus(200)
            ->assertJsonPath('data.invoice_id', $this->invoice->hashed_id)
            ->assertJsonPath('data.client_id', $this->client->hashed_id)
            ->assertJsonPath('data.status_id', (string) PurchaseOrder::STATUS_DRAFT)
            ->assertJsonPath('data.vendor_id', '')
            ->assertJsonPath('data.entity_type', 'purchaseOrder')
            ->assertJsonPath('data.design_id', $purchase_order_design_id)
            ->assertJsonPath('data.line_items.0.cost', 47.25)
            ->assertJsonCount(count((array) $this->invoice->line_items), 'data.line_items');

        $this->assertNotSame($this->encodePrimaryKey($this->invoice->design_id), $response->json('data.design_id'));
        $this->assertNotSame(125.50, $response->json('data.line_items.0.cost'));
        $this->assertSame($purchase_order_count, PurchaseOrder::count());

        $purchase_order = $response->json('data');
        $purchase_order['vendor_id'] = $this->vendor->hashed_id;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/purchase_orders', $purchase_order)
            ->assertStatus(200)
            ->assertJsonPath('data.invoice_id', $this->invoice->hashed_id);

        $purchase_order = PurchaseOrder::find($this->decodePrimaryKey($response->json('data.id')));

        $this->assertSame($this->invoice->id, $purchase_order->invoice_id);
        $this->assertSame(7, $purchase_order->design_id);
        $this->assertSame(47.25, $purchase_order->line_items[0]->cost);
    }

    public function testCloneInvoiceToPurchaseOrderRequiresOneInvoice()
    {
        try {
            $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/invoices/bulk', [
                'action' => 'clone_to_purchase_order',
                'ids' => [$this->invoice->hashed_id, $this->invoice->hashed_id],
            ])->assertStatus(422)
                ->assertJsonValidationErrors(['ids']);
        } finally {
            Atomic::del('127.0.0.1|clone_to_purchase_order|' . $this->company->company_key);
        }
    }


    public function testPurchaseOrderHistory()
    {
        event(new PurchaseOrderWasUpdated($this->purchase_order, $this->company, Ninja::eventVars($this->company, $this->user)));
        event(new PurchaseOrderWasCreated($this->purchase_order, $this->company, Ninja::eventVars($this->company, $this->user)));

        $ar = new ActivityRepository();
        $fields = new \stdClass();
        $fields->user_id = $this->purchase_order->user_id;
        $fields->vendor_id = $this->purchase_order->vendor_id;
        $fields->company_id = $this->purchase_order->company_id;
        $fields->activity_type_id = Activity::UPDATE_PURCHASE_ORDER;
        $fields->purchase_order_id = $this->purchase_order->id;

        $ar->save($fields, $this->purchase_order, Ninja::eventVars($this->company, $this->user));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/purchase_orders/{$this->purchase_order->hashed_id}?include=activities.history")
        ->assertStatus(200);

        $arr = $response->json();

        $activities = $arr['data']['activities'];

        foreach ($activities as $activity) {
            $this->assertTrue(count($activity['history']) >= 1);
        }

    }

    public function testPurchaseOrderBulkActions()
    {

        $po = PurchaseOrder::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $po->service()->createInvitations()->save();

        $i = $po->invitations->first();

        $data = [
            'ids' => [$po->hashed_id],
            'action' => 'download',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/purchase_orders/bulk", $data);

        $response->assertStatus(200);


        $data = [
            'ids' => [$po->hashed_id],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/purchase_orders/bulk", $data);

        $response->assertStatus(200);

        $data = [
            'ids' => [$po->hashed_id],
            'action' => 'restore',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/purchase_orders/bulk", $data)
        ->assertStatus(200);

        $data = [
            'ids' => [$po->hashed_id],
            'action' => 'delete',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/purchase_orders/bulk", $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$po->hashed_id],
            'action' => 'restore',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/purchase_orders/bulk", $data)
        ->assertStatus(200);


        $data = [
            'ids' => [],
            'action' => 'archive',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/purchase_orders/bulk", $data)
        ->assertStatus(302);

        $data = [
            'ids' => [$po->hashed_id],
            'action' => '',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/purchase_orders/bulk", $data)
        ->assertStatus(302);


        $data = [
            'ids' => [$po->hashed_id],
            'action' => 'molly',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/purchase_orders/bulk", $data)
        ->assertStatus(302);
    }

    public function testPurchaseOrderDownloadPDF()
    {
        $i = $this->purchase_order->invitations->first();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/purchase_order/{$i->key}/download");

        $response->assertStatus(200);
        $this->assertTrue($response->headers->get('content-type') == 'application/pdf');
    }


    public function testPurchaseOrderGetWithClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/purchase_orders?client_status=sent'.$this->encodePrimaryKey($this->purchase_order->id));

        $response->assertStatus(200);
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
