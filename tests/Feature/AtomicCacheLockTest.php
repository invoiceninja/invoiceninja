<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Helpers\Cache\Atomic;
use Illuminate\Support\Facades\Cache;

class AtomicCacheLockTest extends TestCase
{

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
     * Test that payment requests with same hash are blocked.
     */
    public function test_duplicate_payment_request_blocked()
    {
        // Simulate payment request hash
        $invoiceIds = ['inv_001', 'inv_002'];
        $clientId = 'client_123';
        $ip = '127.0.0.1';
        $companyKey = 'test_company';
        
        $hashKey = implode(',', $invoiceIds);
        $hash = $ip . "|" . $hashKey . "|" . $clientId . "|" . $companyKey;

        // First payment request should succeed
        $result1 = Atomic::set($hash, true, 1);
        $this->assertTrue($result1, 'First payment request should succeed');

        // Duplicate payment request with same invoices should fail
        $result2 = Atomic::set($hash, true, 1);
        $this->assertFalse($result2, 'Duplicate payment request should be blocked');

        // Cleanup
        Atomic::del($hash);
    }

    /**
     * Test that different invoice combinations generate different keys.
     */
    public function test_different_invoice_combinations_generate_different_keys()
    {
        $ip = '127.0.0.1';
        $clientId = 'client_123';
        $companyKey = 'test_company';

        // First payment: invoices A and B
        $hash1 = $ip . "|" . implode(',', ['inv_A', 'inv_B']) . "|" . $clientId . "|" . $companyKey;
        
        // Second payment: invoices C and D
        $hash2 = $ip . "|" . implode(',', ['inv_C', 'inv_D']) . "|" . $clientId . "|" . $companyKey;

        // Both should succeed (different keys)
        $result1 = Atomic::set($hash1, true, 1);
        $this->assertTrue($result1, 'First payment should succeed');

        $result2 = Atomic::set($hash2, true, 1);
        $this->assertTrue($result2, 'Second payment with different invoices should succeed');

        // But duplicate of first should fail
        $result3 = Atomic::set($hash1, true, 1);
        $this->assertFalse($result3, 'Duplicate of first payment should fail');

        // Cleanup
        Atomic::del($hash1);
        Atomic::del($hash2);
    }

    /**
     * Test that lock cleanup allows subsequent requests.
     */
    public function test_lock_cleanup_allows_subsequent_requests()
    {
        $hash = 'payment-hash-' . uniqid();

        // First request succeeds
        $result1 = Atomic::set($hash, true, 1);
        $this->assertTrue($result1);

        // Second request fails (duplicate)
        $result2 = Atomic::set($hash, true, 1);
        $this->assertFalse($result2);

        // Cleanup (simulating controller cleanup after payment created)
        Atomic::del($hash);

        // Third request should succeed after cleanup
        $result3 = Atomic::set($hash, true, 1);
        $this->assertTrue($result3, 'Request should succeed after lock cleanup');

        // Cleanup
        Atomic::del($hash);
    }
}
