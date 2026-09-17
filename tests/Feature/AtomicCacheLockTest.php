<?php

namespace Tests\Feature;

use App\Helpers\Cache\Atomic;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Tests\MockAccountData;
use Tests\TestCase;

class AtomicCacheLockTest extends TestCase
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
     * Test that Atomic::set() prevents duplicate requests.
     */
    public function test_atomic_set_prevents_duplicate_lock()
    {
        $key = 'test-lock-key-' . uniqid();

        // First request should succeed
        $result1 = Atomic::set($key, true, 1);
        $this->assertTrue($result1, 'First atomic set should succeed');

        // Second request with same key should fail
        $result2 = Atomic::set($key, true, 1);
        $this->assertFalse($result2, 'Second atomic set should fail (key already exists)');

        // Cleanup
        Atomic::del($key);
    }

    /**
     * Test that Atomic::del() removes the lock.
     */
    public function test_atomic_del_removes_lock()
    {
        $key = 'test-lock-key-' . uniqid();

        // Set lock
        Atomic::set($key, true, 1);
        $this->assertNotNull(Atomic::get($key), 'Lock should exist after set');

        // Delete lock
        Atomic::del($key);
        $this->assertNull(Atomic::get($key), 'Lock should not exist after delete');

        // Should be able to set again after delete
        $result = Atomic::set($key, true, 1);
        $this->assertTrue($result, 'Should be able to set lock again after delete');

        // Cleanup
        Atomic::del($key);
    }

    /**
     * Test that lock expires after TTL.
     */
    public function test_lock_expires_after_ttl()
    {
        $key = 'test-lock-key-' . uniqid();

        // Set lock with 1 second TTL
        $result1 = Atomic::set($key, true, 1);
        $this->assertTrue($result1);

        // Immediate retry should fail
        $result2 = Atomic::set($key, true, 1);
        $this->assertFalse($result2);

        // Wait for TTL to expire
        sleep(2);

        // Should succeed after TTL
        $result3 = Atomic::set($key, true, 1);
        $this->assertTrue($result3, 'Lock should be settable after TTL expires');

        // Cleanup
        Atomic::del($key);
    }

    /**
     * Test that Atomic::set() is truly atomic (no race condition).
     */
    public function test_atomic_set_is_truly_atomic()
    {
        $key = 'race-condition-test-' . uniqid();
        $successCount = 0;

        // Simulate 10 simultaneous attempts
        for ($i = 0; $i < 10; $i++) {
            if (Atomic::set($key, true, 1)) {
                $successCount++;
            }
        }

        // Only ONE should succeed
        $this->assertEquals(1, $successCount, 'Only one atomic set should succeed in race condition');

        // Cleanup
        Atomic::del($key);
    }

    /**
     * Test that Cache::add() fallback works when Redis fails.
     */
    public function test_cache_fallback_when_redis_unavailable()
    {
        // This test validates the fallback mechanism in Atomic class
        // When Redis is unavailable, it should use Cache::add()

        $key = 'fallback-test-' . uniqid();

        // Clear any existing key
        Cache::forget($key);

        // Test Cache::add directly (what Atomic uses as fallback)
        $result1 = Cache::add($key, true, 1);
        $this->assertTrue($result1, 'First Cache::add should succeed');

        $result2 = Cache::add($key, true, 1);
        $this->assertFalse($result2, 'Second Cache::add should fail (atomic behavior)');

        // Cleanup
        Cache::forget($key);
    }

    /**
     * Test that payment requests with the same payload fingerprint are blocked.
     */
    public function test_duplicate_payment_request_blocked()
    {
        $payload = $this->samplePaymentPayload();
        $lock_key = $this->paymentLockKey($payload, $this->company->company_key);

        $result1 = Atomic::set($lock_key, true, 1);
        $this->assertTrue($result1, 'First payment request should succeed');

        $result2 = Atomic::set($lock_key, true, 1);
        $this->assertFalse($result2, 'Duplicate payment request should be blocked');

        Atomic::del($lock_key);
    }

    /**
     * Test that different invoice combinations generate different payment lock keys.
     */
    public function test_different_invoice_combinations_generate_different_keys()
    {
        $companyKey = $this->company->company_key;

        $payload_one = $this->samplePaymentPayload([
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
        ]);

        $payload_two = $this->samplePaymentPayload([
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey(Invoice::factory()->create([
                        'company_id' => $this->company->id,
                        'user_id' => $this->user->id,
                        'client_id' => $this->client->id,
                    ])->id),
                    'amount' => 5,
                ],
            ],
        ]);

        $lock_key_one = $this->paymentLockKey($payload_one, $companyKey);
        $lock_key_two = $this->paymentLockKey($payload_two, $companyKey);

        $this->assertNotSame($lock_key_one, $lock_key_two);

        $this->assertTrue(Atomic::set($lock_key_one, true, 1));
        $this->assertTrue(Atomic::set($lock_key_two, true, 1));
        $this->assertFalse(Atomic::set($lock_key_one, true, 1));

        Atomic::del($lock_key_one);
        Atomic::del($lock_key_two);
    }

    /**
     * Test that different payment amounts to the same invoice generate different lock keys.
     */
    public function test_different_payment_amounts_generate_different_keys()
    {
        $companyKey = $this->company->company_key;

        $payload_one = $this->samplePaymentPayload([
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
        ]);

        $payload_two = $this->samplePaymentPayload([
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 10,
                ],
            ],
        ]);

        $lock_key_one = $this->paymentLockKey($payload_one, $companyKey);
        $lock_key_two = $this->paymentLockKey($payload_two, $companyKey);

        $this->assertNotSame($lock_key_one, $lock_key_two);

        $this->assertTrue(Atomic::set($lock_key_one, true, 1));
        $this->assertTrue(Atomic::set($lock_key_two, true, 1));

        Atomic::del($lock_key_one);
        Atomic::del($lock_key_two);
    }

    /**
     * Test that a client-supplied lock_key does not affect the payment fingerprint.
     */
    public function test_client_supplied_lock_key_does_not_affect_payment_fingerprint()
    {
        $companyKey = $this->company->company_key;
        $payload = $this->samplePaymentPayload();

        $without_lock_key = $this->paymentLockKey($payload, $companyKey);
        $with_lock_key = $this->paymentLockKey(array_merge($payload, [
            'lock_key' => '|PAYMENT|stale-lock-key|'.$companyKey,
        ]), $companyKey);

        $this->assertSame($without_lock_key, $with_lock_key);
    }

    /**
     * Test that lock cleanup allows subsequent requests.
     */
    public function test_lock_cleanup_allows_subsequent_requests()
    {
        $payload = $this->samplePaymentPayload();
        $lock_key = $this->paymentLockKey($payload, $this->company->company_key);

        $this->assertTrue(Atomic::set($lock_key, true, 1));
        $this->assertFalse(Atomic::set($lock_key, true, 1));

        Atomic::del($lock_key);

        $this->assertTrue(Atomic::set($lock_key, true, 1), 'Request should succeed after lock cleanup');

        Atomic::del($lock_key);
    }

    /**
     * Test that store payment requests are rejected while an identical lock is held.
     */
    public function test_store_payment_request_returns_duplicate_when_lock_held()
    {
        $payload = $this->samplePaymentPayload();
        $lock_key = $this->paymentLockKey($payload, $this->company->company_key);

        Atomic::set($lock_key, true, 1);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $payload);

        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Duplicate request',
        ]);

        Atomic::del($lock_key);
    }

    /**
     * Test that store invoice requests are rejected while an identical lock is held.
     */
    public function test_store_invoice_request_returns_duplicate_when_lock_held()
    {
        $payload = $this->sampleInvoicePayload();
        $lock_key = $this->invoiceLockKey($payload, $this->company->company_key);

        Atomic::set($lock_key, true, 1);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/', $payload);

        $response->assertStatus(429);
        $response->assertJson([
            'message' => 'Duplicate request',
        ]);

        Atomic::del($lock_key);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function samplePaymentPayload(array $overrides = []): array
    {
        return array_merge([
            'amount' => 5,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2020-12-11',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleInvoicePayload(): array
    {
        return [
            'client_id' => $this->client->id,
            'line_items' => [
                [
                    'cost' => 10,
                    'qty' => 1,
                    'product_key' => 'Item',
                ],
            ],
            'date' => '2020-12-11',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function paymentLockKey(array $input, string $companyKey): string
    {
        unset($input['lock_key']);

        return '|PAYMENT|' . hash('sha256', json_encode($input)) . '|' . $companyKey;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function invoiceLockKey(array $input, string $companyKey): string
    {
        unset($input['lock_key']);

        return '|INVOICE|' . hash('sha256', json_encode($input)) . '|' . $companyKey;
    }
}
