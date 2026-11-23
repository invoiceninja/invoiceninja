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

namespace Tests\Unit\Services\Payment;

use App\Jobs\Payment\EmailPayment;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Tests\MockAccountData;
use Tests\TestCase;

class SendEmailTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    /**
     * Payment drivers call sendEmail() from inside the transaction that
     * creates the payment (CHIP hosted, Stripe BACS), so the receipt must
     * wait for that transaction to commit. Otherwise a rollback still
     * leaves the client with a receipt for a payment that was never saved.
     */
    public function testPaymentReceiptIsQueuedOnlyAfterTheTransactionCommits(): void
    {
        Bus::fake([EmailPayment::class]);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'user_id' => $this->user->id,
        ]);

        $payment->service()->sendEmail();

        Bus::assertDispatchedTimes(EmailPayment::class, 1);
        Bus::assertDispatched(
            EmailPayment::class,
            fn (EmailPayment $job): bool => $job->afterCommit === true
        );
    }
}
