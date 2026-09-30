<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Factory\InvoiceItemFactory;
use App\Models\PurchaseOrder;
use App\Services\Email\Email;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\MockAccountData;
use Tests\TestCase;

class PurchaseOrderBalanceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Bus::fake([Email::class]);
    }

    public function testCreateThenMarkSentBalanceIsAmount(): void
    {
        $purchase_order = $this->createDraft(1000);

        $this->assertDraft($purchase_order, 1000);

        $this->markSent($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testCreateThenEmailBalanceIsAmount(): void
    {
        $purchase_order = $this->createDraft(1000);

        $this->email($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testMultipleUpdatesEchoingApiResponseThenMarkSent(): void
    {
        $purchase_order = $this->createDraft(1000);
        $payload = $this->show($purchase_order);

        foreach ([1000, 1000, 1000] as $cost) {
            $payload['line_items'] = $this->lineItems($cost);
            $payload = $this->update($purchase_order, $payload);
            $purchase_order->refresh();
            $this->assertDraft($purchase_order, $cost);
        }

        $this->markSent($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testMultipleUpdatesEchoingApiResponseThenEmail(): void
    {
        $purchase_order = $this->createDraft(1000);
        $payload = $this->show($purchase_order);

        foreach ([800, 900, 1000] as $cost) {
            $payload['line_items'] = $this->lineItems($cost);
            $payload = $this->update($purchase_order, $payload);
            $purchase_order->refresh();
            $this->assertDraft($purchase_order, $cost);
        }

        $this->email($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testMultipleUpdatesSendingOnlyLineItemsThenMarkSent(): void
    {
        $purchase_order = $this->createDraft(1000);

        foreach ([600, 800, 1000] as $cost) {
            $this->update($purchase_order, [
                'vendor_id' => $this->vendor->hashed_id,
                'line_items' => $this->lineItems($cost),
            ]);
            $purchase_order->refresh();
            $this->assertDraft($purchase_order, $cost);
        }

        $this->markSent($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testMultipleUpdatesSendingBalanceEqualToAmountThenMarkSent(): void
    {
        $purchase_order = $this->createDraft(1000);

        foreach ([700, 850, 1000] as $cost) {
            $this->update($purchase_order, [
                'vendor_id' => $this->vendor->hashed_id,
                'amount' => $cost,
                'balance' => $cost,
                'line_items' => $this->lineItems($cost),
            ]);
            $purchase_order->refresh();
            $this->assertDraft($purchase_order, $cost);
        }

        $this->markSent($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testMultipleUpdatesSendingBalanceEqualToAmountThenEmail(): void
    {
        $purchase_order = $this->createDraft(1000);

        foreach ([1000, 1000, 1000] as $cost) {
            $this->update($purchase_order, [
                'vendor_id' => $this->vendor->hashed_id,
                'amount' => $cost,
                'balance' => $cost,
                'line_items' => $this->lineItems($cost),
            ]);
            $purchase_order->refresh();
            $this->assertDraft($purchase_order, $cost);
        }

        $this->email($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testUpdateWithMarkSentQueryAfterEdits(): void
    {
        $purchase_order = $this->createDraft(1000);

        $this->update($purchase_order, [
            'vendor_id' => $this->vendor->hashed_id,
            'line_items' => $this->lineItems(1000),
        ]);
        $this->update($purchase_order, [
            'vendor_id' => $this->vendor->hashed_id,
            'line_items' => $this->lineItems(1000),
        ]);

        $this->withHeaders($this->headers())
            ->putJson('/api/v1/purchase_orders/'.$purchase_order->hashed_id.'?mark_sent=true', [
                'vendor_id' => $this->vendor->hashed_id,
                'line_items' => $this->lineItems(1000),
            ])
            ->assertStatus(200);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testUpdateWithSendEmailQueryAfterEdits(): void
    {
        $purchase_order = $this->createDraft(1000);

        $this->update($purchase_order, [
            'vendor_id' => $this->vendor->hashed_id,
            'line_items' => $this->lineItems(1000),
            'balance' => 1000,
            'amount' => 1000,
        ]);

        $this->withHeaders($this->headers())
            ->putJson('/api/v1/purchase_orders/'.$purchase_order->hashed_id.'?send_email=true', [
                'vendor_id' => $this->vendor->hashed_id,
                'line_items' => $this->lineItems(1000),
                'balance' => 1000,
                'amount' => 1000,
            ])
            ->assertStatus(200);

        $this->assertSentOnce($purchase_order, 1000);
    }

    public function testUpdateThenBulkMarkSentAndEmail(): void
    {
        $purchase_order = $this->createDraft(1000);

        $this->update($purchase_order, [
            'vendor_id' => $this->vendor->hashed_id,
            'line_items' => $this->lineItems(1000),
            'balance' => 1000,
        ]);
        $this->update($purchase_order, [
            'vendor_id' => $this->vendor->hashed_id,
            'line_items' => $this->lineItems(1000),
            'balance' => 1000,
        ]);

        $this->markSent($purchase_order);
        $this->email($purchase_order);

        $this->assertSentOnce($purchase_order, 1000);
    }

    /** @return array<int, array<string, mixed>> */
    private function lineItems(float $cost): array
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = $cost;

        return [(array) $item];
    }

    private function createDraft(float $cost): PurchaseOrder
    {
        $response = $this->withHeaders($this->headers())
            ->postJson('/api/v1/purchase_orders', [
                'vendor_id' => $this->vendor->hashed_id,
                'status_id' => PurchaseOrder::STATUS_DRAFT,
                'line_items' => $this->lineItems($cost),
            ])
            ->assertStatus(200);

        return PurchaseOrder::findOrFail($this->decodePrimaryKey($response->json('data.id')));
    }

    /** @return array<string, mixed> */
    private function show(PurchaseOrder $purchase_order): array
    {
        return $this->withHeaders($this->headers())
            ->getJson('/api/v1/purchase_orders/'.$purchase_order->hashed_id)
            ->assertStatus(200)
            ->json('data');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function update(PurchaseOrder $purchase_order, array $payload): array
    {
        return $this->withHeaders($this->headers())
            ->putJson('/api/v1/purchase_orders/'.$purchase_order->hashed_id, $payload)
            ->assertStatus(200)
            ->json('data');
    }

    private function markSent(PurchaseOrder $purchase_order): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/purchase_orders/bulk', [
                'ids' => [$purchase_order->hashed_id],
                'action' => 'mark_sent',
            ])
            ->assertStatus(200);
    }

    private function email(PurchaseOrder $purchase_order): void
    {
        $this->withHeaders($this->headers())
            ->postJson('/api/v1/purchase_orders/bulk', [
                'ids' => [$purchase_order->hashed_id],
                'action' => 'email',
            ])
            ->assertStatus(200);
    }

    private function assertDraft(PurchaseOrder $purchase_order, float $amount): void
    {
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchase_order->status_id);
        $this->assertEquals($amount, $purchase_order->amount);
        $this->assertEquals(0, $purchase_order->balance, 'Draft PO balance should stay 0 until sent');
    }

    private function assertSentOnce(PurchaseOrder $purchase_order, float $amount): void
    {
        $purchase_order->refresh();

        $this->assertSame(PurchaseOrder::STATUS_SENT, $purchase_order->status_id);
        $this->assertEquals($amount, $purchase_order->amount);
        $this->assertEquals($amount, $purchase_order->balance, 'Sent PO balance should equal amount, not 2x');
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }
}
