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

use App\Models\Invoice;
use App\Models\Webhook;
use App\Observers\InvoiceObserver;
use App\Jobs\Util\WebhookHandler;
use App\Repositories\InvoiceRepository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

class InvoiceDeleteWebhookTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * Deleting an invoice must drive InvoiceObserver::updated() with is_deleted = true,
     * so EVENT_DELETE_INVOICE is dispatched (and EVENT_ARCHIVE_INVOICE is not).
     *
     * The observer is afterCommit=true, so it does not run inside the test transaction.
     * We capture the model instance the events fire on, then replay the observer against
     * that real post-delete state with Bus faked to assert what would be dispatched.
     */
    public function testDeleteDispatchesDeleteWebhookNotArchive(): void
    {
        $captured = ['updated' => null, 'deleted' => null];

        Invoice::updated(function (Invoice $i) use (&$captured) {
            $captured['updated'] = $i;
        });
        Invoice::deleted(function (Invoice $i) use (&$captured) {
            $captured['deleted'] = $i;
        });

        (new InvoiceRepository())->delete($this->invoice);

        // The delete must have emitted an `updated` event carrying is_deleted = true.
        $this->assertNotNull($captured['updated'], 'delete did not fire the updated event - EVENT_DELETE_INVOICE can never dispatch');
        $this->assertTrue((bool) $captured['updated']->is_deleted);

        // Subscribe to both delete + update + archive so we prove the resolver picks DELETE.
        $this->makeHook(Webhook::EVENT_DELETE_INVOICE);
        $this->makeHook(Webhook::EVENT_UPDATE_INVOICE);
        $this->makeHook(Webhook::EVENT_ARCHIVE_INVOICE);

        Bus::fake();

        $observer = new InvoiceObserver();
        $observer->updated($captured['updated']);
        if ($captured['deleted']) {
            $observer->deleted($captured['deleted']);
        }

        $events = [];
        foreach (Bus::dispatched(WebhookHandler::class) as $job) {
            $r = new \ReflectionProperty($job, 'event_id');
            $events[] = $r->getValue($job);
        }

        $this->assertContains(Webhook::EVENT_DELETE_INVOICE, $events, 'EVENT_DELETE_INVOICE was not dispatched');
        $this->assertNotContains(Webhook::EVENT_ARCHIVE_INVOICE, $events, 'a delete should not dispatch EVENT_ARCHIVE_INVOICE');
        $this->assertNotContains(Webhook::EVENT_UPDATE_INVOICE, $events, 'a delete should not dispatch EVENT_UPDATE_INVOICE');
    }

    private function makeHook(int $event_id): void
    {
        $w = new Webhook();
        $w->company_id = $this->company->id;
        $w->user_id = $this->user->id;
        $w->event_id = $event_id;
        $w->target_url = 'https://example.com/hook';
        $w->save();
    }
}
