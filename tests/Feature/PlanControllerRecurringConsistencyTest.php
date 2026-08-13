<?php

namespace Tests\Feature;

use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Admin\Http\Controllers\PlanController;
use ReflectionMethod;
use Tests\MockAccountData;
use Tests\TestCase;

class PlanControllerRecurringConsistencyTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(PlanController::class)) {
            $this->markTestSkipped('Admin module is not available.');
        }

        $this->makeTestData();
    }

    public function test_reuses_invoice_recurring_when_subscription_matches(): void
    {
        $subscription = Subscription::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_ANNUALLY,
            'min_seats_limit' => 1,
            'max_seats_limit' => 1,
            'trial_duration' => 0,
            'webhook_configuration' => [],
            'price' => 96,
        ]);

        $this->recurring_invoice->subscription_id = $subscription->id;
        $this->recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $this->recurring_invoice->saveQuietly();

        $other = RecurringInvoice::query()
            ->where('client_id', $this->client->id)
            ->where('id', '!=', $this->recurring_invoice->id)
            ->firstOrFail();

        $this->invoice->subscription_id = $subscription->id;
        $this->invoice->recurring_id = $this->recurring_invoice->id;
        $this->invoice->saveQuietly();

        $before_count = RecurringInvoice::query()
            ->where('client_id', $this->client->id)
            ->count();

        $controller = new PlanController();
        $controller->invoice = $this->invoice->fresh();
        $controller->subscription = $subscription;
        $controller->client = $this->client;
        $controller->recurring_invoice = $other;

        $method = new ReflectionMethod(PlanController::class, 'checkRecurringInvoiceIsConsistent');
        $method->setAccessible(true);
        $method->invoke($controller);

        $this->assertSame($this->recurring_invoice->id, $controller->recurring_invoice->id);
        $this->assertSame(
            $before_count,
            RecurringInvoice::query()
                ->where('client_id', $this->client->id)
                ->count()
        );
    }
}
