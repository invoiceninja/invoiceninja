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

use App\Jobs\Util\WebhookHandler;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Bus;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * End-to-end coverage that every lifecycle webhook fires for the correct event.
 *
 * The webhook observers are registered with $afterCommit = true, so they do not run
 * inside the test's wrapping transaction (DatabaseTransactions). We therefore wire the
 * SAME observer to run synchronously on the model events, then drive each entity through
 * its REAL repository lifecycle (create / update / archive / restore / delete) and assert
 * - via Bus::fake() - that the correct EVENT_* webhook is dispatched (and that archive vs
 * delete are not confused). This exercises the full chain: operation -> model event ->
 * observer event-resolution + subscription filter guards -> WebhookHandler dispatch.
 */
class WebhookLifecycleTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testClientWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Client::class,
            \App\Observers\ClientObserver::class,
            \App\Repositories\ClientRepository::class,
            fn () => \App\Models\Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]),
            ['create' => Webhook::EVENT_CREATE_CLIENT, 'update' => Webhook::EVENT_UPDATE_CLIENT, 'archive' => Webhook::EVENT_ARCHIVE_CLIENT, 'restore' => Webhook::EVENT_RESTORE_CLIENT, 'delete' => Webhook::EVENT_DELETE_CLIENT],
        );
    }

    public function testInvoiceWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Invoice::class,
            \App\Observers\InvoiceObserver::class,
            \App\Repositories\InvoiceRepository::class,
            fn () => \App\Models\Invoice::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'client_id' => $this->client->id]),
            ['create' => Webhook::EVENT_CREATE_INVOICE, 'update' => Webhook::EVENT_UPDATE_INVOICE, 'archive' => Webhook::EVENT_ARCHIVE_INVOICE, 'restore' => Webhook::EVENT_RESTORE_INVOICE, 'delete' => Webhook::EVENT_DELETE_INVOICE],
        );
    }

    public function testQuoteWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Quote::class,
            \App\Observers\QuoteObserver::class,
            \App\Repositories\QuoteRepository::class,
            fn () => \App\Models\Quote::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'client_id' => $this->client->id]),
            ['create' => Webhook::EVENT_CREATE_QUOTE, 'update' => Webhook::EVENT_UPDATE_QUOTE, 'archive' => Webhook::EVENT_ARCHIVE_QUOTE, 'restore' => Webhook::EVENT_RESTORE_QUOTE, 'delete' => Webhook::EVENT_DELETE_QUOTE],
        );
    }

    public function testCreditWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Credit::class,
            \App\Observers\CreditObserver::class,
            \App\Repositories\CreditRepository::class,
            fn () => \App\Models\Credit::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'client_id' => $this->client->id]),
            ['create' => Webhook::EVENT_CREATE_CREDIT, 'update' => Webhook::EVENT_UPDATE_CREDIT, 'archive' => Webhook::EVENT_ARCHIVE_CREDIT, 'restore' => Webhook::EVENT_RESTORE_CREDIT, 'delete' => Webhook::EVENT_DELETE_CREDIT],
        );
    }

    public function testPaymentWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Payment::class,
            \App\Observers\PaymentObserver::class,
            \App\Repositories\PaymentRepository::class,
            fn () => \App\Models\Payment::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'client_id' => $this->client->id, 'amount' => 10]),
            ['create' => Webhook::EVENT_CREATE_PAYMENT, 'update' => Webhook::EVENT_UPDATE_PAYMENT, 'archive' => Webhook::EVENT_ARCHIVE_PAYMENT, 'restore' => Webhook::EVENT_RESTORE_PAYMENT, 'delete' => Webhook::EVENT_DELETE_PAYMENT],
        );
    }

    public function testVendorWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Vendor::class,
            \App\Observers\VendorObserver::class,
            \App\Repositories\VendorRepository::class,
            fn () => \App\Models\Vendor::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'currency_id' => 1]),
            ['create' => Webhook::EVENT_CREATE_VENDOR, 'update' => Webhook::EVENT_UPDATE_VENDOR, 'archive' => Webhook::EVENT_ARCHIVE_VENDOR, 'restore' => Webhook::EVENT_RESTORE_VENDOR, 'delete' => Webhook::EVENT_DELETE_VENDOR],
        );
    }

    public function testExpenseWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Expense::class,
            \App\Observers\ExpenseObserver::class,
            \App\Repositories\ExpenseRepository::class,
            fn () => \App\Models\Expense::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]),
            ['create' => Webhook::EVENT_CREATE_EXPENSE, 'update' => Webhook::EVENT_UPDATE_EXPENSE, 'archive' => Webhook::EVENT_ARCHIVE_EXPENSE, 'restore' => Webhook::EVENT_RESTORE_EXPENSE, 'delete' => Webhook::EVENT_DELETE_EXPENSE],
        );
    }

    public function testTaskWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Task::class,
            \App\Observers\TaskObserver::class,
            \App\Repositories\TaskRepository::class,
            fn () => \App\Models\Task::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]),
            ['create' => Webhook::EVENT_CREATE_TASK, 'update' => Webhook::EVENT_UPDATE_TASK, 'archive' => Webhook::EVENT_ARCHIVE_TASK, 'restore' => Webhook::EVENT_RESTORE_TASK, 'delete' => Webhook::EVENT_DELETE_TASK],
        );
    }

    public function testProjectWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Project::class,
            \App\Observers\ProjectObserver::class,
            \App\Repositories\ProjectRepository::class,
            fn () => \App\Models\Project::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'client_id' => $this->client->id]),
            ['create' => Webhook::EVENT_PROJECT_CREATE, 'update' => Webhook::EVENT_PROJECT_UPDATE, 'archive' => Webhook::EVENT_ARCHIVE_PROJECT, 'restore' => Webhook::EVENT_RESTORE_PROJECT, 'delete' => Webhook::EVENT_PROJECT_DELETE],
        );
    }

    public function testProductWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\Product::class,
            \App\Observers\ProductObserver::class,
            \App\Repositories\ProductRepository::class,
            fn () => \App\Models\Product::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]),
            ['create' => Webhook::EVENT_CREATE_PRODUCT, 'update' => Webhook::EVENT_UPDATE_PRODUCT, 'archive' => Webhook::EVENT_ARCHIVE_PRODUCT, 'restore' => Webhook::EVENT_RESTORE_PRODUCT, 'delete' => Webhook::EVENT_DELETE_PRODUCT],
        );
    }

    public function testPurchaseOrderWebhooks(): void
    {
        $this->runLifecycle(
            \App\Models\PurchaseOrder::class,
            \App\Observers\PurchaseOrderObserver::class,
            \App\Repositories\PurchaseOrderRepository::class,
            fn () => \App\Models\PurchaseOrder::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id, 'vendor_id' => $this->vendor->id]),
            ['create' => Webhook::EVENT_CREATE_PURCHASE_ORDER, 'update' => Webhook::EVENT_UPDATE_PURCHASE_ORDER, 'archive' => Webhook::EVENT_ARCHIVE_PURCHASE_ORDER, 'restore' => Webhook::EVENT_RESTORE_PURCHASE_ORDER, 'delete' => Webhook::EVENT_DELETE_PURCHASE_ORDER],
        );
    }

    /**
     * @param array{create:int,update:int,archive:int,restore:int,delete:int} $events
     */
    private function runLifecycle(string $model, string $observer, string $repoClass, callable $factory, array $events): void
    {
        // Subscribe to ALL lifecycle events so the observer must resolve the CORRECT one.
        foreach ($events as $event_id) {
            $this->makeHook($event_id);
        }

        $this->wireObserverSynchronously($model, $observer);

        $repo = app($repoClass);
        $label = class_basename($model);

        // CREATE
        Bus::fake();
        $instance = $factory();
        $this->assertContains($events['create'], $this->dispatchedWebhookEvents(), "{$label}: CREATE webhook did not fire");

        // UPDATE
        Bus::fake();
        $instance->forceFill(['updated_at' => now()->addMinute()])->save();
        $this->assertContains($events['update'], $this->dispatchedWebhookEvents(), "{$label}: UPDATE webhook did not fire");

        // ARCHIVE
        Bus::fake();
        $repo->archive($instance);
        $afterArchive = $this->dispatchedWebhookEvents();
        $this->assertContains($events['archive'], $afterArchive, "{$label}: ARCHIVE webhook did not fire");
        $this->assertNotContains($events['delete'], $afterArchive, "{$label}: ARCHIVE incorrectly dispatched the DELETE webhook");

        // RESTORE
        Bus::fake();
        $repo->restore($instance);
        $this->assertContains($events['restore'], $this->dispatchedWebhookEvents(), "{$label}: RESTORE webhook did not fire");

        // DELETE
        Bus::fake();
        $repo->delete($instance);
        $afterDelete = $this->dispatchedWebhookEvents();
        $this->assertContains($events['delete'], $afterDelete, "{$label}: DELETE webhook did not fire");
        $this->assertNotContains($events['archive'], $afterDelete, "{$label}: DELETE incorrectly dispatched the ARCHIVE webhook");
    }

    /**
     * The real observer is afterCommit (silent inside the test transaction). Register the
     * same observer's handlers to run synchronously on the model events instead.
     */
    private function wireObserverSynchronously(string $model, string $observer): void
    {
        $obs = new $observer();

        $model::created(fn ($m) => $obs->created($m));
        $model::updated(fn ($m) => $obs->updated($m));
        $model::deleted(fn ($m) => $obs->deleted($m));
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
