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

use Tests\TestCase;
use App\Models\Quote;
use App\Models\Credit;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\PurchaseOrder;
use App\DataMapper\InvoiceItem;
use App\Models\RecurringInvoice;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * @test
 * @covers App\Http\Controllers\ActivityController
 */
class MaxAmountTest extends TestCase
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

    }

    public function testInvoiceMaxAmount()
    {
        $item = new InvoiceItem();
        $item->cost = 10000000000000000;
        $item->quantity = 100;

        $data = [
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'line_items' => [$item]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices', $data);

        $response->assertStatus(422);

        $i = Invoice::factory()->create($data);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/invoices/{$i->hashed_id}", $data);

        $response->assertStatus(422);
    }


    public function testCreditMaxAmount()
    {
        $item = new InvoiceItem();
        $item->cost = 10000000000000000;
        $item->quantity = 100;

        $data = [
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'line_items' => [$item]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/credits', $data);

        $response->assertStatus(422);

        $i = Credit::factory()->create($data);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/credits/{$i->hashed_id}", $data);

        $response->assertStatus(422);
    }


    public function testQuoteMaxAmount()
    {
        $item = new InvoiceItem();
        $item->cost = 10000000000000000;
        $item->quantity = 100;

        $data = [
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'line_items' => [$item]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/quotes', $data);

        $response->assertStatus(422);

        $i = Quote::factory()->create($data);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/quotes/{$i->hashed_id}", $data);

        $response->assertStatus(422);
    }

    public function testPurchaseOrderMaxAmount()
    {
        $item = new InvoiceItem();
        $item->cost = 10000000000000000;
        $item->quantity = 100;

        $data = [
            'user_id' => $this->user->id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $this->company->id,
            'line_items' => [$item]
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/purchase_orders', $data);

        $response->assertStatus(422);

        $i = PurchaseOrder::factory()->create($data);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/purchase_orders/{$i->hashed_id}", $data);

        $response->assertStatus(422);
    }

    public function testRecurringInvoiceMaxAmount()
    {
        $item = new InvoiceItem();
        $item->cost = 10000000000000000;
        $item->quantity = 100;

        $data = [
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'line_items' => [$item],
            'frequency_id' => 5
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices', $data);

        $response->assertStatus(422);

        $i = RecurringInvoice::factory()->create($data);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson("/api/v1/recurring_invoices/{$i->hashed_id}", $data);

        $response->assertStatus(422);
    }
}
