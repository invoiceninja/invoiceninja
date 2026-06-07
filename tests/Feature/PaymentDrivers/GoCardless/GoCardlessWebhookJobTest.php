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

namespace Tests\Feature\PaymentDrivers\GoCardless;

use App\Jobs\Mail\PaymentFailedMailer;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\PaymentDrivers\GoCardless\Jobs\GoCardlessWebhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\MockAccountData;
use Tests\TestCase;

class GoCardlessWebhookJobTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    private CompanyGateway $gocardlessGateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $cg = new CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'b9886f9257f0c6ee7c302f1c74475f6c';
        $cg->require_cvv = false;
        $cg->require_billing_address = false;
        $cg->require_shipping_address = false;
        $cg->update_details = false;
        $cg->config = encrypt(json_encode([
            'accessToken' => 'fake_access_token',
            'webhookSecret' => 'shh',
            'testMode' => true,
        ]));
        $cg->fees_and_limits = [];
        $cg->save();

        $this->gocardlessGateway = $cg;
    }

    private function makePayment(string $reference, int $status = Payment::STATUS_PENDING): Payment
    {
        return Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
            'transaction_reference' => $reference,
            'status_id' => $status,
            'amount' => 10,
        ]);
    }

    private function dispatchEvents(array $events): void
    {
        (new GoCardlessWebhook($events, $this->company->company_key, $this->gocardlessGateway->id))->handle();
    }

    public function test_missing_company_gateway_returns_without_throwing(): void
    {
        $job = new GoCardlessWebhook(
            [['id' => 'EV1', 'resource_type' => 'payments', 'action' => 'confirmed', 'links' => ['payment' => 'PMx']]],
            $this->company->company_key,
            99999999
        );

        $job->handle();

        $this->assertTrue(true);
    }

    public function test_confirmed_event_sets_status_completed(): void
    {
        $payment = $this->makePayment('PM_CONF_1', Payment::STATUS_PENDING);

        $this->dispatchEvents([[
            'id' => 'EV1',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['payment' => 'PM_CONF_1'],
        ]]);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
    }

    public function test_paid_out_event_sets_status_completed(): void
    {
        $payment = $this->makePayment('PM_PAIDOUT_1', Payment::STATUS_PENDING);

        $this->dispatchEvents([[
            'id' => 'EV2',
            'resource_type' => 'payments',
            'action' => 'paid_out',
            'links' => ['payment' => 'PM_PAIDOUT_1', 'payout' => 'PO1'],
        ]]);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
    }

    public function test_confirmed_does_not_regress_refunded_payment(): void
    {
        $payment = $this->makePayment('PM_REF_1', Payment::STATUS_REFUNDED);

        $this->dispatchEvents([[
            'id' => 'EV3',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['payment' => 'PM_REF_1'],
        ]]);

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status_id);
    }

    public function test_confirmed_without_links_payment_key_does_not_throw(): void
    {
        $this->dispatchEvents([[
            'id' => 'EV4',
            'resource_type' => 'payments',
            'action' => 'confirmed',
            'links' => ['mandate' => 'MD1'],
        ]]);

        $this->assertTrue(true);
    }

    public function test_failed_event_marks_payment_failed_and_dispatches_mailer(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $payment = $this->makePayment('PM_FAIL_1', Payment::STATUS_COMPLETED);

        $this->dispatchEvents([[
            'id' => 'EV5',
            'resource_type' => 'payments',
            'action' => 'failed',
            'links' => ['payment' => 'PM_FAIL_1'],
            'details' => ['description' => 'Insufficient funds'],
        ]]);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status_id);
        Bus::assertDispatched(PaymentFailedMailer::class, 1);
    }

    public function test_failed_event_is_idempotent_on_retry(): void
    {
        Bus::fake([PaymentFailedMailer::class]);

        $payment = $this->makePayment('PM_FAIL_2', Payment::STATUS_FAILED);

        $this->dispatchEvents([[
            'id' => 'EV6',
            'resource_type' => 'payments',
            'action' => 'failed',
            'links' => ['payment' => 'PM_FAIL_2'],
            'details' => ['description' => 'Already failed'],
        ]]);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status_id);
        Bus::assertNotDispatched(PaymentFailedMailer::class);
    }

    public function test_malformed_event_does_not_abort_remaining_events(): void
    {
        $payment = $this->makePayment('PM_BATCH_1', Payment::STATUS_PENDING);

        $this->dispatchEvents([
            // malformed: missing resource_type, missing links — will throw inside the loop
            ['id' => 'EV_BAD', 'action' => 'confirmed'],
            // valid event after the bad one — should still process
            [
                'id' => 'EV_GOOD',
                'resource_type' => 'payments',
                'action' => 'confirmed',
                'links' => ['payment' => 'PM_BATCH_1'],
            ],
        ]);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status_id);
    }
}
