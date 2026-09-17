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

use App\Events\Quote\QuoteWasApproved;
use App\Jobs\Util\WebhookHandler;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\PurchaseOrder;
use App\Models\Webhook;
use App\Utils\Ninja;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * End-to-end coverage for the non-lifecycle "action" webhooks that are dispatched outside
 * the model observers (via BaseModel::sendEvent() from the MarkSent services, and via the
 * QuoteApprovedWebhook listener). These dispatch WebhookHandler directly, so no observer
 * wiring is needed - we drive the real service/event and assert with Bus::fake().
 */
class WebhookActionTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testSentInvoiceWebhook(): void
    {
        $this->makeHook(Webhook::EVENT_SENT_INVOICE);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id'    => $this->user->id,
            'client_id'  => $this->client->id,
            'status_id'  => Invoice::STATUS_DRAFT,
            'amount'     => 10,
            'balance'    => 10,
        ]);

        Bus::fake();
        $invoice->service()->markSent(true)->save();

        $this->assertContains(Webhook::EVENT_SENT_INVOICE, $this->dispatchedWebhookEvents(), 'SENT_INVOICE webhook did not fire');
    }

    public function testSentQuoteWebhook(): void
    {
        $this->makeHook(Webhook::EVENT_SENT_QUOTE);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'user_id'    => $this->user->id,
            'client_id'  => $this->client->id,
            'status_id'  => Quote::STATUS_DRAFT,
        ]);

        Bus::fake();
        $quote->service()->markSent(true)->save();

        $this->assertContains(Webhook::EVENT_SENT_QUOTE, $this->dispatchedWebhookEvents(), 'SENT_QUOTE webhook did not fire');
    }

    public function testSentCreditWebhook(): void
    {
        $this->makeHook(Webhook::EVENT_SENT_CREDIT);

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id'    => $this->user->id,
            'client_id'  => $this->client->id,
            'status_id'  => Credit::STATUS_DRAFT,
        ]);

        Bus::fake();
        $credit->service()->markSent(true)->save();

        $this->assertContains(Webhook::EVENT_SENT_CREDIT, $this->dispatchedWebhookEvents(), 'SENT_CREDIT webhook did not fire');
    }

    public function testSentPurchaseOrderWebhook(): void
    {
        $this->makeHook(Webhook::EVENT_SENT_PURCHASE_ORDER);

        $po = PurchaseOrder::factory()->create([
            'company_id' => $this->company->id,
            'user_id'    => $this->user->id,
            'vendor_id'  => $this->vendor->id,
            'status_id'  => PurchaseOrder::STATUS_DRAFT,
        ]);

        Bus::fake();
        (new \App\Services\PurchaseOrder\MarkSent($po->vendor, $po))->run();

        $this->assertContains(Webhook::EVENT_SENT_PURCHASE_ORDER, $this->dispatchedWebhookEvents(), 'SENT_PURCHASE_ORDER webhook did not fire');
    }

    public function testApproveQuoteWebhook(): void
    {
        $this->makeHook(Webhook::EVENT_APPROVE_QUOTE);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'user_id'    => $this->user->id,
            'client_id'  => $this->client->id,
            'status_id'  => Quote::STATUS_SENT,
        ]);

        // QuoteApprovedWebhook implements ShouldQueue, so under Bus::fake() the listener is
        // intercepted before handle() runs. Drive its real handle() to exercise the dispatch.
        $event = new QuoteWasApproved($this->client->contacts->first(), $quote, $quote->company, Ninja::eventVars());

        Bus::fake();
        (new \App\Listeners\Quote\QuoteApprovedWebhook())->handle($event);

        $this->assertContains(Webhook::EVENT_APPROVE_QUOTE, $this->dispatchedWebhookEvents(), 'APPROVE_QUOTE webhook did not fire');
    }

    public function testRemindInvoiceWebhook(): void
    {
        $this->makeHook(Webhook::EVENT_REMIND_INVOICE);

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id'    => $this->user->id,
            'client_id'  => $this->client->id,
            'status_id'  => Invoice::STATUS_SENT,
            'amount'     => 10,
            'balance'    => 10,
        ]);

        // Production dispatch seam (App\Jobs\Util\ReminderJob fires this once a reminder is due).
        Bus::fake();
        $invoice->sendEvent(Webhook::EVENT_REMIND_INVOICE, 'client');

        $this->assertContains(Webhook::EVENT_REMIND_INVOICE, $this->dispatchedWebhookEvents(), 'REMIND_INVOICE webhook did not fire');
    }

    public function testRemindQuoteWebhook(): void
    {
        $this->makeHook(Webhook::EVENT_REMIND_QUOTE);

        $quote = Quote::factory()->create([
            'company_id' => $this->company->id,
            'user_id'    => $this->user->id,
            'client_id'  => $this->client->id,
            'status_id'  => Quote::STATUS_SENT,
        ]);

        // Production dispatch seam (App\Jobs\Util\QuoteReminderJob fires this once a reminder is due).
        Bus::fake();
        $quote->sendEvent(Webhook::EVENT_REMIND_QUOTE, 'client');

        $this->assertContains(Webhook::EVENT_REMIND_QUOTE, $this->dispatchedWebhookEvents(), 'REMIND_QUOTE webhook did not fire');
    }

    private function makeHook(int $event_id): void
    {
        $w = new Webhook();
        $w->company_id = $this->company->id;
        $w->user_id = $this->user->id;
        $w->event_id = $event_id;
        $w->target_url = 'https://example.com/hook/' . $event_id;
        $w->save();
    }

    /**
     * @return array<int>
     */
    private function dispatchedWebhookEvents(): array
    {
        $events = [];

        foreach (Bus::dispatched(WebhookHandler::class) as $job) {
            $events[] = (new \ReflectionProperty($job, 'event_id'))->getValue($job);
        }

        return $events;
    }
}
