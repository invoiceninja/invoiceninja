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

namespace Tests\Feature\Payments;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\DataMapper\InvoiceItem;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Test to confirm and prevent the credit double-spend bug on refund.
 *
 * Scenario:
 * - Invoice $50,000
 * - Credit Note $40,000 available
 * - Payment using $40,000 credit + $10,000 manual = $50,000 total
 * - User refunds $50,000 (instead of just $10,000)
 * - This "refunds" the credit (restores balance)
 * - User can then re-apply the credit (double spend!)
 * - Credit balance becomes -$40,000
 */
class CreditRefundDoubleSpendTest extends TestCase
{
    use MakesHash;
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

    /**
     * Test that refunding a payment with credits properly updates the client's credit_balance.
     * 
     * This confirms the bug where client.credit_balance is not updated when credits are
     * restored during a refund, leading to a potential double-spend scenario.
     */
    public function testCreditRefundUpdatesClientCreditBalance()
    {
        // Create a fresh client for this test
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Create invoice for $50,000
        $item = new InvoiceItem();
        $item->cost = 50000;
        $item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'line_items' => [$item],
        ]);

        $invoice->service()->markSent()->save();

        $this->assertEquals(50000, $invoice->balance);

        // Create credit for $40,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 40000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 40000,
            'balance' => 40000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);

        $credit->service()->markSent()->save();

        $this->assertEquals(40000, $credit->balance);

        // Update client credit_balance (normally done by markSent)
        $client->credit_balance = 40000;
        $client->save();

        $this->assertEquals(40000, $client->fresh()->credit_balance);

        // Create payment: $10,000 cash + $40,000 credit = $50,000 applied to invoice
        $payment_data = [
            'amount' => 10000, // Manual payment amount (cash portion)
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 50000, // Total applied to invoice
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $credit->hashed_id,
                    'amount' => 40000, // Credit amount used
                ],
            ],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $payment_data);

        $response->assertStatus(200);

        $arr = $response->json();
        $payment_id = $arr['data']['id'];

        // Verify payment was created correctly
        $payment = Payment::find($this->decodePrimaryKey($payment_id));
        $this->assertNotNull($payment);
        $this->assertEquals(10000, $payment->amount); // Cash portion only

        // Verify invoice is fully paid
        $invoice = $invoice->fresh();
        $this->assertEquals(0, $invoice->balance);

        // Verify credit is fully used
        $credit = $credit->fresh();
        $this->assertEquals(0, $credit->balance);

        // Verify client credit_balance is reduced
        $client = $client->fresh();
        $this->assertEquals(0, $client->credit_balance);

        // Now attempt to refund $50,000 (the full invoice amount)
        $refund_data = [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 50000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 50000,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $refund_data);

        $response->assertStatus(200);

        // Refresh all entities
        $payment = $payment->fresh();
        $invoice = $invoice->fresh();
        $credit = $credit->fresh();
        $client = $client->fresh();

        // Verify payment is refunded
        // Note: Only $10,000 should be gateway refunded, $40,000 comes from credit restoration
        $this->assertEquals(10000, $payment->refunded);

        // Verify invoice balance is restored
        $this->assertEquals(50000, $invoice->balance);

        // Verify credit balance is restored
        $this->assertEquals(40000, $credit->balance);

        // THIS IS THE BUG: client credit_balance should also be restored to $40,000
        // If this assertion fails, it confirms the bug
        $this->assertEquals(40000, $client->credit_balance, 
            'Client credit_balance should be restored when credit is refunded. ' .
            'Current value: ' . $client->credit_balance);
    }

    /**
     * Test that a user cannot double-spend credits after a refund.
     * 
     * This test confirms that after refunding a payment that used credits:
     * 1. The credit balance is properly restored
     * 2. The client credit_balance is properly updated
     * 3. Re-applying the credit does not cause negative credit balance
     */
    public function testPreventCreditDoubleSpendAfterRefund()
    {
        // Create a fresh client
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Create invoice for $50,000
        $item = new InvoiceItem();
        $item->cost = 50000;
        $item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'line_items' => [$item],
        ]);

        $invoice->service()->markSent()->save();

        // Create credit for $40,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 40000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 40000,
            'balance' => 40000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);

        $credit->service()->markSent()->save();

        // Update client credit_balance
        $client->credit_balance = 40000;
        $client->save();

        // Step 1: Create payment with credit
        $payment_data = [
            'amount' => 10000,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 50000,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $credit->hashed_id,
                    'amount' => 40000,
                ],
            ],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $payment_data);

        $response->assertStatus(200);
        $payment_id = $response->json()['data']['id'];

        // Verify state after payment
        $this->assertEquals(0, $client->fresh()->credit_balance);
        $this->assertEquals(0, $credit->fresh()->balance);
        $this->assertEquals(0, $invoice->fresh()->balance);

        // Step 2: Refund the full amount
        $refund_data = [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 50000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 50000,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $refund_data);

        $response->assertStatus(200);

        // After refund, credit should be restored
        $credit = $credit->fresh();
        $client = $client->fresh();
        $invoice = $invoice->fresh();

        $this->assertEquals(40000, $credit->balance, 'Credit balance should be restored after refund');
        $this->assertEquals(50000, $invoice->balance, 'Invoice balance should be restored after refund');

        // BUG CHECK: client credit_balance should match credit balance
        $this->assertEquals(40000, $client->credit_balance,
            'Client credit_balance should be restored when credit is refunded. ' .
            'If this is 0 but credit.balance is 40000, the bug exists.');

        // Step 3: Re-apply the credit to the same invoice
        // This should work correctly without causing negative balance
        $payment_data2 = [
            'amount' => 10000,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 50000,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $credit->hashed_id,
                    'amount' => 40000,
                ],
            ],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $payment_data2);

        $response->assertStatus(200);

        // Verify final state
        $credit = $credit->fresh();
        $client = $client->fresh();
        $invoice = $invoice->fresh();

        $this->assertEquals(0, $credit->balance, 'Credit balance should be 0 after re-application');
        $this->assertEquals(0, $invoice->balance, 'Invoice should be paid');

        // CRITICAL: Client credit_balance should NOT be negative
        $this->assertGreaterThanOrEqual(0, $client->credit_balance,
            'Client credit_balance should never be negative! Current value: ' . $client->credit_balance .
            '. This indicates a double-spend bug.');
    }

    /**
     * Test the exact scenario described by the user:
     * - Invoice $50,000
     * - Credit $40,000
     * - Payment: $40,000 credit + $10,000 manual
     * - Refund $50,000 (should only refund $10,000 cash, restore $40,000 credit)
     * - Re-apply credit should NOT cause -$40,000 credit balance
     */
    public function testExactUserScenario()
    {
        // Create fresh client
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
            'balance' => 0,
            'paid_to_date' => 0,
        ]);

        // Invoice $50,000
        $invoice_item = new InvoiceItem();
        $invoice_item->cost = 50000;
        $invoice_item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'line_items' => [$invoice_item],
        ]);

        $invoice->service()->markSent()->save();

        // Credit $40,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 40000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 40000,
            'balance' => 40000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);

        $credit->service()->markSent()->save();

        // Set initial client credit_balance
        $client->credit_balance = 40000;
        $client->save();

        // Assertions before payment
        $this->assertEquals(50000, $invoice->fresh()->balance);
        $this->assertEquals(40000, $credit->fresh()->balance);
        $this->assertEquals(40000, $client->fresh()->credit_balance);

        // === STEP 1: Create payment with $40,000 credit + $10,000 cash ===
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 10000,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 50000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 40000]],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment_id = $response->json()['data']['id'];

        // Verify state after payment
        $invoice->refresh();
        $credit->refresh();
        $client->refresh();

        $this->assertEquals(0, $invoice->balance, 'Invoice should be fully paid');
        $this->assertEquals(0, $credit->balance, 'Credit should be fully used');
        $this->assertEquals(0, $client->credit_balance, 'Client credit_balance should be 0');

        // === STEP 2: Refund $50,000 (full invoice amount) ===
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 50000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 50000]],
        ]);

        $response->assertStatus(200);

        // Verify state after refund
        $invoice->refresh();
        $credit->refresh();
        $client->refresh();
        
        $payment = Payment::find($this->decodePrimaryKey($payment_id));

        // Only $10,000 should be actually refunded (gateway refund)
        // The $40,000 credit portion is "restored" not "refunded"
        $this->assertEquals(10000, $payment->refunded, 
            'Only the cash portion ($10,000) should be gateway refunded');

        // Invoice balance should be restored
        $this->assertEquals(50000, $invoice->balance, 'Invoice balance should be restored to $50,000');

        // Credit balance should be restored
        $this->assertEquals(40000, $credit->balance, 'Credit balance should be restored to $40,000');

        // CLIENT CREDIT BALANCE SHOULD ALSO BE RESTORED - THIS IS THE BUG
        $this->assertEquals(40000, $client->credit_balance,
            'BUG: Client credit_balance should be restored to $40,000 when credit is refunded. ' .
            'Actual value: ' . $client->credit_balance);

        // === STEP 3: Re-apply the credit (should work without double spend) ===
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 10000,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 50000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 40000]],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);

        // Final verification
        $invoice->refresh();
        $credit->refresh();
        $client->refresh();

        $this->assertEquals(0, $invoice->balance, 'Invoice should be paid again');
        $this->assertEquals(0, $credit->balance, 'Credit should be used');

        // CRITICAL CHECK: Credit balance should NOT be negative
        $this->assertGreaterThanOrEqual(0, $client->credit_balance,
            'CRITICAL BUG: Client credit_balance is negative (' . $client->credit_balance . ')! ' .
            'This indicates a double-spend vulnerability.');

        // Credit balance should be exactly 0, not negative
        $this->assertEquals(0, $client->credit_balance,
            'Client credit_balance should be exactly 0 after re-applying the credit. ' .
            'Actual: ' . $client->credit_balance);
    }

    /**
     * Test partial credit usage with full refund.
     * 
     * Scenario:
     * - Credit $40,000 available
     * - Payment uses only $20,000 of credit + $10,000 cash = $30,000 to invoice
     * - Credit still has $20,000 remaining
     * - Refund the full $30,000
     * - Credit should be restored to $40,000
     * - Client credit_balance should be restored to $40,000
     */
    public function testPartialCreditUsageWithFullRefund()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Invoice $30,000
        $invoice_item = new InvoiceItem();
        $invoice_item->cost = 30000;
        $invoice_item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 30000,
            'balance' => 30000,
            'line_items' => [$invoice_item],
        ]);
        $invoice->service()->markSent()->save();

        // Credit $40,000 (more than needed)
        $credit_item = new InvoiceItem();
        $credit_item->cost = 40000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 40000,
            'balance' => 40000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);
        $credit->service()->markSent()->save();

        $client->credit_balance = 40000;
        $client->save();

        // Create payment: $10,000 cash + $20,000 credit (partial) = $30,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 10000,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 30000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 20000]], // Only use $20k of $40k
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment_id = $response->json()['data']['id'];

        // Verify partial credit usage
        $credit->refresh();
        $client->refresh();

        $this->assertEquals(20000, $credit->balance, 'Credit should have $20,000 remaining');
        $this->assertEquals(20000, $client->credit_balance, 'Client credit_balance should be $20,000');
        $this->assertEquals(0, $invoice->fresh()->balance, 'Invoice should be paid');

        // Refund the full $30,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 30000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 30000]],
        ]);

        $response->assertStatus(200);

        // Verify full restoration
        $credit->refresh();
        $client->refresh();
        $invoice->refresh();

        $this->assertEquals(40000, $credit->balance, 
            'Credit should be fully restored to $40,000. Actual: ' . $credit->balance);
        $this->assertEquals(40000, $client->credit_balance, 
            'Client credit_balance should be restored to $40,000. Actual: ' . $client->credit_balance);
        $this->assertEquals(30000, $invoice->balance, 'Invoice balance should be restored');
    }

    /**
     * Test partial refund of a payment that used partial credit.
     * 
     * Scenario:
     * - Credit $40,000 available
     * - Payment uses $30,000 credit + $20,000 cash = $50,000 to invoice
     * - Credit has $10,000 remaining
     * - Refund only $15,000 (less than credit portion)
     * - Only credit should be restored (no gateway refund)
     * - Credit balance should go from $10,000 to $25,000
     */
    public function testPartialRefundWithinCreditPortion()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Invoice $50,000
        $invoice_item = new InvoiceItem();
        $invoice_item->cost = 50000;
        $invoice_item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'line_items' => [$invoice_item],
        ]);
        $invoice->service()->markSent()->save();

        // Credit $40,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 40000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 40000,
            'balance' => 40000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);
        $credit->service()->markSent()->save();

        $client->credit_balance = 40000;
        $client->save();

        // Payment: $20,000 cash + $30,000 credit = $50,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 20000,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 50000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 30000]],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment_id = $response->json()['data']['id'];

        // Verify state after payment
        $credit->refresh();
        $client->refresh();

        $this->assertEquals(10000, $credit->balance, 'Credit should have $10,000 remaining');
        $this->assertEquals(10000, $client->credit_balance, 'Client credit_balance should be $10,000');

        // Partial refund: $15,000 (within the $30,000 credit portion used)
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 15000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 15000]],
        ]);

        $response->assertStatus(200);

        $payment = Payment::find($this->decodePrimaryKey($payment_id));
        $credit->refresh();
        $client->refresh();
        $invoice->refresh();

        // Since $15,000 < $30,000 credit used, only credit should be restored (no gateway refund)
        $this->assertEquals(0, $payment->refunded, 
            'No gateway refund should occur when refund is within credit portion. Actual: ' . $payment->refunded);

        // Credit balance: $10,000 + $15,000 = $25,000
        $this->assertEquals(25000, $credit->balance, 
            'Credit balance should be $25,000 ($10k remaining + $15k restored). Actual: ' . $credit->balance);
        $this->assertEquals(25000, $client->credit_balance, 
            'Client credit_balance should be $25,000. Actual: ' . $client->credit_balance);

        // Invoice balance should be $15,000
        $this->assertEquals(15000, $invoice->balance, 
            'Invoice balance should be $15,000. Actual: ' . $invoice->balance);
    }

    /**
     * Test partial refund that exceeds credit portion and requires gateway refund.
     * 
     * Scenario:
     * - Payment uses $30,000 credit + $20,000 cash = $50,000
     * - Refund $40,000 (exceeds $30,000 credit, needs $10,000 gateway refund)
     */
    public function testPartialRefundExceedingCreditPortion()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Invoice $50,000
        $invoice_item = new InvoiceItem();
        $invoice_item->cost = 50000;
        $invoice_item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'line_items' => [$invoice_item],
        ]);
        $invoice->service()->markSent()->save();

        // Credit $30,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 30000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 30000,
            'balance' => 30000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);
        $credit->service()->markSent()->save();

        $client->credit_balance = 30000;
        $client->save();

        // Payment: $20,000 cash + $30,000 credit = $50,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 20000,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 50000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 30000]],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment_id = $response->json()['data']['id'];

        // Verify credit fully used
        $this->assertEquals(0, $credit->fresh()->balance);
        $this->assertEquals(0, $client->fresh()->credit_balance);

        // Refund $40,000 (more than credit portion)
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 40000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 40000]],
        ]);

        $response->assertStatus(200);

        $payment = Payment::find($this->decodePrimaryKey($payment_id));
        $credit->refresh();
        $client->refresh();
        $invoice->refresh();

        // Credit fully restored ($30,000), plus $10,000 gateway refund
        $this->assertEquals(10000, $payment->refunded, 
            'Gateway refund should be $10,000 ($40k refund - $30k credit restore). Actual: ' . $payment->refunded);

        $this->assertEquals(30000, $credit->balance, 
            'Credit should be fully restored to $30,000. Actual: ' . $credit->balance);
        $this->assertEquals(30000, $client->credit_balance, 
            'Client credit_balance should be $30,000. Actual: ' . $client->credit_balance);

        $this->assertEquals(40000, $invoice->balance, 
            'Invoice balance should be $40,000. Actual: ' . $invoice->balance);
    }

    /**
     * Test multiple sequential refunds on the same payment.
     * 
     * Scenario:
     * - Payment: $30,000 credit + $20,000 cash = $50,000
     * - First refund: $15,000 (restores $15,000 credit)
     * - Second refund: $20,000 (restores remaining $15,000 credit + $5,000 gateway)
     */
    public function testMultipleSequentialRefunds()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Invoice $50,000
        $invoice_item = new InvoiceItem();
        $invoice_item->cost = 50000;
        $invoice_item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'line_items' => [$invoice_item],
        ]);
        $invoice->service()->markSent()->save();

        // Credit $30,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 30000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 30000,
            'balance' => 30000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);
        $credit->service()->markSent()->save();

        $client->credit_balance = 30000;
        $client->save();

        // Payment: $20,000 cash + $30,000 credit = $50,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 20000,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 50000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 30000]],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment_id = $response->json()['data']['id'];

        // Verify initial state
        $this->assertEquals(0, $credit->fresh()->balance);
        $this->assertEquals(0, $client->fresh()->credit_balance);

        // === First refund: $15,000 ===
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 15000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 15000]],
        ]);

        $response->assertStatus(200);

        $payment = Payment::find($this->decodePrimaryKey($payment_id));
        $credit->refresh();
        $client->refresh();

        // First refund: only credit restored, no gateway refund
        $this->assertEquals(0, $payment->refunded, 'First refund should not touch gateway');
        $this->assertEquals(15000, $credit->balance, 'Credit balance should be $15,000');
        $this->assertEquals(15000, $client->credit_balance, 'Client credit_balance should be $15,000');

        // === Second refund: $20,000 ===
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 20000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 20000]],
        ]);

        $response->assertStatus(200);

        $payment->refresh();
        $credit->refresh();
        $client->refresh();
        $invoice->refresh();

        // Second refund: $15,000 remaining credit + $5,000 gateway
        $this->assertEquals(5000, $payment->refunded, 
            'Gateway refund should be $5,000 ($20k - $15k remaining credit). Actual: ' . $payment->refunded);
        $this->assertEquals(30000, $credit->balance, 
            'Credit should be fully restored to $30,000. Actual: ' . $credit->balance);
        $this->assertEquals(30000, $client->credit_balance, 
            'Client credit_balance should be $30,000. Actual: ' . $client->credit_balance);
        $this->assertEquals(35000, $invoice->balance, 
            'Invoice balance should be $35,000 ($15k + $20k refunded). Actual: ' . $invoice->balance);
    }

    /**
     * Test multiple credits used in a single payment with partial refund.
     * 
     * Scenario:
     * - Credit A: $20,000
     * - Credit B: $15,000
     * - Payment: $20,000 Credit A + $15,000 Credit B + $15,000 cash = $50,000
     * - Refund $25,000 (should restore Credit A fully + $5,000 of Credit B)
     */
    public function testMultipleCreditsWithPartialRefund()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Invoice $50,000
        $invoice_item = new InvoiceItem();
        $invoice_item->cost = 50000;
        $invoice_item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'line_items' => [$invoice_item],
        ]);
        $invoice->service()->markSent()->save();

        // Credit A: $20,000
        $credit_item_a = new InvoiceItem();
        $credit_item_a->cost = 20000;
        $credit_item_a->quantity = 1;

        $credit_a = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 20000,
            'balance' => 20000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item_a],
        ]);
        $credit_a->service()->markSent()->save();

        // Credit B: $15,000
        $credit_item_b = new InvoiceItem();
        $credit_item_b->cost = 15000;
        $credit_item_b->quantity = 1;

        $credit_b = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 15000,
            'balance' => 15000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item_b],
        ]);
        $credit_b->service()->markSent()->save();

        $client->credit_balance = 35000; // $20k + $15k
        $client->save();

        // Payment: $15,000 cash + $20,000 Credit A + $15,000 Credit B = $50,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 15000,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 50000]],
            'credits' => [
                ['credit_id' => $credit_a->hashed_id, 'amount' => 20000],
                ['credit_id' => $credit_b->hashed_id, 'amount' => 15000],
            ],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment_id = $response->json()['data']['id'];

        // Verify both credits used
        $this->assertEquals(0, $credit_a->fresh()->balance);
        $this->assertEquals(0, $credit_b->fresh()->balance);
        $this->assertEquals(0, $client->fresh()->credit_balance);

        // Refund $25,000 (should restore Credit A fully + $5,000 of Credit B)
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment_id,
            'client_id' => $client->hashed_id,
            'amount' => 25000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 25000]],
        ]);

        $response->assertStatus(200);

        $payment = Payment::find($this->decodePrimaryKey($payment_id));
        $credit_a->refresh();
        $credit_b->refresh();
        $client->refresh();
        $invoice->refresh();

        // No gateway refund (all from credits)
        $this->assertEquals(0, $payment->refunded, 
            'No gateway refund should occur. Actual: ' . $payment->refunded);

        // Total credit restored: $25,000 (distributed across credits in order)
        // The exact distribution depends on iteration order, but total should be $25,000
        $total_credit_restored = $credit_a->balance + $credit_b->balance;
        $this->assertEquals(25000, $total_credit_restored, 
            'Total credit restored should be $25,000. Actual: ' . $total_credit_restored);

        $this->assertEquals(25000, $client->credit_balance, 
            'Client credit_balance should be $25,000. Actual: ' . $client->credit_balance);

        $this->assertEquals(25000, $invoice->balance, 
            'Invoice balance should be $25,000. Actual: ' . $invoice->balance);
    }

    /**
     * Test that a credit used across multiple payments refunds correctly.
     * 
     * Scenario:
     * - Credit $40,000
     * - Payment 1 uses $25,000 → credit.balance = $15,000
     * - Payment 2 uses $15,000 → credit.balance = $0
     * - Refund Payment 1's $25,000 → credit.balance should be $25,000
     * - Refund Payment 2's $15,000 → credit.balance should be $40,000
     */
    public function testCreditUsedAcrossMultiplePayments()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Invoice 1: $25,000
        $invoice1_item = new InvoiceItem();
        $invoice1_item->cost = 25000;
        $invoice1_item->quantity = 1;

        $invoice1 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 25000,
            'balance' => 25000,
            'line_items' => [$invoice1_item],
        ]);
        $invoice1->service()->markSent()->save();

        // Invoice 2: $15,000
        $invoice2_item = new InvoiceItem();
        $invoice2_item->cost = 15000;
        $invoice2_item->quantity = 1;

        $invoice2 = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 15000,
            'balance' => 15000,
            'line_items' => [$invoice2_item],
        ]);
        $invoice2->service()->markSent()->save();

        // Credit $40,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 40000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 40000,
            'balance' => 40000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);
        $credit->service()->markSent()->save();

        $client->credit_balance = 40000;
        $client->save();

        // Payment 1: Use $25,000 credit for Invoice 1
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 0,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice1->hashed_id, 'amount' => 25000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 25000]],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment1_id = $response->json()['data']['id'];

        $credit->refresh();
        $client->refresh();

        $this->assertEquals(15000, $credit->balance, 'Credit should have $15,000 remaining after Payment 1');
        $this->assertEquals(15000, $client->credit_balance, 'Client credit_balance should be $15,000');

        // Payment 2: Use remaining $15,000 credit for Invoice 2
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', [
            'amount' => 0,
            'client_id' => $client->hashed_id,
            'invoices' => [['invoice_id' => $invoice2->hashed_id, 'amount' => 15000]],
            'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 15000]],
            'date' => now()->format('Y-m-d'),
            'idempotency_key' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(200);
        $payment2_id = $response->json()['data']['id'];

        $credit->refresh();
        $client->refresh();

        $this->assertEquals(0, $credit->balance, 'Credit should be fully used after Payment 2');
        $this->assertEquals(0, $client->credit_balance, 'Client credit_balance should be $0');

        // Refund Payment 1's $25,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment1_id,
            'client_id' => $client->hashed_id,
            'amount' => 25000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice1->hashed_id, 'amount' => 25000]],
        ]);

        $response->assertStatus(200);

        $credit->refresh();
        $client->refresh();

        $this->assertEquals(25000, $credit->balance, 
            'Credit should be $25,000 after refunding Payment 1. Actual: ' . $credit->balance);
        $this->assertEquals(25000, $client->credit_balance, 
            'Client credit_balance should be $25,000. Actual: ' . $client->credit_balance);

        // Refund Payment 2's $15,000
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', [
            'id' => $payment2_id,
            'client_id' => $client->hashed_id,
            'amount' => 15000,
            'date' => now()->format('Y-m-d'),
            'invoices' => [['invoice_id' => $invoice2->hashed_id, 'amount' => 15000]],
        ]);

        $response->assertStatus(200);

        $credit->refresh();
        $client->refresh();

        $this->assertEquals(40000, $credit->balance, 
            'Credit should be fully restored to $40,000. Actual: ' . $credit->balance);
        $this->assertEquals(40000, $client->credit_balance, 
            'Client credit_balance should be $40,000. Actual: ' . $client->credit_balance);
    }

    /**
     * Test that client credit_balance never goes negative even with complex scenarios.
     * 
     * This is the ultimate sanity check for the double-spend bug.
     */
    public function testClientCreditBalanceNeverNegative()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'credit_balance' => 0,
        ]);

        // Invoice $100,000
        $invoice_item = new InvoiceItem();
        $invoice_item->cost = 100000;
        $invoice_item->quantity = 1;

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 100000,
            'balance' => 100000,
            'line_items' => [$invoice_item],
        ]);
        $invoice->service()->markSent()->save();

        // Credit $50,000
        $credit_item = new InvoiceItem();
        $credit_item->cost = 50000;
        $credit_item->quantity = 1;

        $credit = Credit::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'status_id' => Credit::STATUS_SENT,
            'amount' => 50000,
            'balance' => 50000,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth()->format('Y-m-d'),
            'line_items' => [$credit_item],
        ]);
        $credit->service()->markSent()->save();

        $client->credit_balance = 50000;
        $client->save();

        // Cycle 1: Pay with credit, refund, pay again
        for ($cycle = 1; $cycle <= 3; $cycle++) {
            // Pay $50,000 with credit + $50,000 cash
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/payments', [
                'amount' => 50000,
                'client_id' => $client->hashed_id,
                'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 100000]],
                'credits' => [['credit_id' => $credit->hashed_id, 'amount' => 50000]],
                'date' => now()->format('Y-m-d'),
                'idempotency_key' => Str::uuid()->toString(),
            ]);

            $response->assertStatus(200);
            $payment_id = $response->json()['data']['id'];

            $client->refresh();
            $this->assertGreaterThanOrEqual(0, $client->credit_balance,
                "Cycle $cycle: Client credit_balance should not be negative after payment. Value: " . $client->credit_balance);

            // Refund the full amount
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/payments/refund', [
                'id' => $payment_id,
                'client_id' => $client->hashed_id,
                'amount' => 100000,
                'date' => now()->format('Y-m-d'),
                'invoices' => [['invoice_id' => $invoice->hashed_id, 'amount' => 100000]],
            ]);

            $response->assertStatus(200);

            $client->refresh();
            $credit->refresh();

            $this->assertGreaterThanOrEqual(0, $client->credit_balance,
                "Cycle $cycle: Client credit_balance should not be negative after refund. Value: " . $client->credit_balance);

            $this->assertEquals(50000, $credit->balance,
                "Cycle $cycle: Credit balance should be restored to $50,000. Value: " . $credit->balance);
            
            $this->assertEquals(50000, $client->credit_balance,
                "Cycle $cycle: Client credit_balance should be $50,000. Value: " . $client->credit_balance);
        }
    }
}
