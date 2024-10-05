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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;


class ActivityApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

    }

    public function testActivityInvoiceNotes()
    {
        $data = [
            'entity' => 'invoices',
            'entity_id' => $this->invoice->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityCreditNotes()
    {
        $data = [
            'entity' => 'credits',
            'entity_id' => $this->credit->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityQuoteNotes()
    {
        $data = [
            'entity' => 'quotes',
            'entity_id' => $this->quote->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }


    public function testActivityClientNotes()
    {
        $data = [
            'entity' => 'clients',
            'entity_id' => $this->client->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }


    public function testActivityRecurringInvoiceNotes()
    {
        $data = [
            'entity' => 'recurring_invoices',
            'entity_id' => $this->recurring_invoice->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }


    public function testActivityExpenseNotes()
    {
        $data = [
            'entity' => 'expenses',
            'entity_id' => $this->expense->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityRecurringExpenseNotes()
    {
        $data = [
            'entity' => 'recurring_expenses',
            'entity_id' => $this->recurring_expense->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }


    public function testActivityVendorNotes()
    {
        $data = [
            'entity' => 'vendors',
            'entity_id' => $this->vendor->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityPurchaseOrderNotes()
    {
        $data = [
            'entity' => 'purchase_orders',
            'entity_id' => $this->purchase_order->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityTaskNotes()
    {
        $data = [
            'entity' => 'tasks',
            'entity_id' => $this->task->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityProjectNotes()
    {
        $data = [
            'entity' => 'projects',
            'entity_id' => $this->project->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityPaymentNotes()
    {
        $data = [
            'entity' => 'payments',
            'entity_id' => $this->payment->hashed_id,
            'notes' => 'These are notes'
        ];

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/activities/notes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('These are notes', $arr['data']['notes']);
    }

    public function testActivityEntity()
    {

        $invoice = $this->company->invoices()->first();

        $invoice->service()->markSent()->markPaid()->markDeleted()->handleRestore()->save();

        $data = [
            'entity' => 'invoice',
            'entity_id' => $invoice->hashed_id
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/activities/entity', $data);

        $response->assertStatus(200);


    }

    public function testActivityGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities/');

        $response->assertStatus(200);
    }

    public function testActivityGetWithReact()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/activities?react=true');

        $response->assertStatus(200);
    }
}
