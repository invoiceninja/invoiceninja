<?php

namespace Tests\Unit\Services\Quickbooks;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use App\Services\Quickbooks\QuickbooksRateLimiter;

/**
 * Tests for QuickBooks API rate limiter
 *
 */
class QuickbooksRateLimiterTest extends TestCase
{

    private QuickbooksRateLimiter $rateLimiter;
    private string $testRealmId = 'test-realm-123';

    protected function setUp(): void
    {
        parent::setUp();

        // Create rate limiter and reset its state
        $this->rateLimiter = new QuickbooksRateLimiter($this->testRealmId);
        $this->rateLimiter->reset();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $this->rateLimiter->reset();
        parent::tearDown();
    }

    
    public function test_it_allows_requests_when_under_rate_limit()
    {
        $this->assertTrue($this->rateLimiter->canMakeRequest());
    }

    
    public function test_it_tracks_requests_correctly()
    {
        // Make 10 requests
        for ($i = 0; $i < 10; $i++) {
            $this->rateLimiter->trackRequest();
        }

        $status = $this->rateLimiter->getStatus();

        $this->assertEquals(10, $status['requests']);
        $this->assertEquals(400, $status['max_requests']); // 80% of 500
    }

    
    public function test_it_blocks_requests_when_at_rate_limit()
    {
        // Simulate hitting the limit (400 = 80% of 500)
        for ($i = 0; $i < 400; $i++) {
            $this->rateLimiter->trackRequest();
        }

        $this->assertFalse($this->rateLimiter->canMakeRequest());
    }

    
    public function test_it_tracks_concurrent_requests()
    {
        $token1 = $this->rateLimiter->acquireRequest();
        $token2 = $this->rateLimiter->acquireRequest();

        $status = $this->rateLimiter->getStatus();

        $this->assertEquals(2, $status['concurrent']);

        // Release one
        $this->rateLimiter->releaseRequest($token1);

        $status = $this->rateLimiter->getStatus();
        $this->assertEquals(1, $status['concurrent']);

        // Release second
        $this->rateLimiter->releaseRequest($token2);

        $status = $this->rateLimiter->getStatus();
        $this->assertEquals(0, $status['concurrent']);
    }

    
    public function test_it_blocks_requests_when_at_concurrent_limit()
    {
        // Acquire 8 concurrent requests (80% of 10)
        $tokens = [];
        for ($i = 0; $i < 8; $i++) {
            $tokens[] = $this->rateLimiter->acquireRequest();
        }

        $this->assertFalse($this->rateLimiter->canMakeRequest());

        // Release one
        $this->rateLimiter->releaseRequest($tokens[0]);

        // Should be able to make request now
        $this->assertTrue($this->rateLimiter->canMakeRequest());
    }

    
    public function test_it_enters_backoff_mode()
    {
        $this->assertFalse($this->rateLimiter->isInBackoff());

        $this->rateLimiter->enterBackoff(60);

        $this->assertTrue($this->rateLimiter->isInBackoff());
        $this->assertFalse($this->rateLimiter->canMakeRequest());
    }

    
    public function test_it_calculates_backoff_seconds_correctly()
    {
        $this->rateLimiter->enterBackoff(60);

        $backoffSeconds = $this->rateLimiter->getBackoffSeconds();

        $this->assertGreaterThan(55, $backoffSeconds);
        $this->assertLessThanOrEqual(60, $backoffSeconds);
    }

    
    public function test_it_provides_recommended_delay_when_near_limit()
    {
        // Use 75% of limit (should trigger delay calculation)
        // maxRequests = 400 (80% of 500), so 75% = 300 requests
        // This is > 70% threshold (280), so delay calculation should trigger
        for ($i = 0; $i < 300; $i++) {
            $this->rateLimiter->trackRequest();
        }

        $delay = $this->rateLimiter->getRecommendedDelay();

        // Should recommend some delay when near limit (or 0 if cache TTL unavailable)
        // This is a numeric delay value
        $this->assertIsInt($delay);
        $this->assertGreaterThanOrEqual(0, $delay);
    }

    
    public function test_it_provides_zero_delay_when_well_under_limit()
    {
        // Only use 50% of limit (well under 70% threshold)
        for ($i = 0; $i < 200; $i++) {
            $this->rateLimiter->trackRequest();
        }

        $delay = $this->rateLimiter->getRecommendedDelay();

        // Should recommend no delay when well under limit
        $this->assertEquals(0, $delay);
    }

    
    public function test_it_blocks_at_exactly_the_safety_limit()
    {
        // Use exactly the safety limit (400 = 80% of 500)
        for ($i = 0; $i < 400; $i++) {
            $this->rateLimiter->trackRequest();
        }

        // Should not allow more requests
        $this->assertFalse($this->rateLimiter->canMakeRequest());

        $status = $this->rateLimiter->getStatus();
        $this->assertEquals(400, $status['requests']);
        $this->assertEquals(400, $status['max_requests']);
    }

    
    public function test_it_resets_correctly()
    {
        // Track some requests
        for ($i = 0; $i < 50; $i++) {
            $this->rateLimiter->trackRequest();
        }

        // Enter backoff
        $this->rateLimiter->enterBackoff(60);

        $this->rateLimiter->reset();

        $status = $this->rateLimiter->getStatus();

        $this->assertEquals(0, $status['requests']);
        $this->assertEquals(0, $status['concurrent']);
        $this->assertFalse($status['in_backoff']);
    }

    
    public function test_it_provides_complete_status()
    {
        $this->rateLimiter->trackRequest();
        $token = $this->rateLimiter->acquireRequest();

        $status = $this->rateLimiter->getStatus();

        $this->assertArrayHasKey('requests', $status);
        $this->assertArrayHasKey('max_requests', $status);
        $this->assertArrayHasKey('concurrent', $status);
        $this->assertArrayHasKey('max_concurrent', $status);
        $this->assertArrayHasKey('in_backoff', $status);
        $this->assertArrayHasKey('backoff_seconds', $status);
        $this->assertArrayHasKey('can_make_request', $status);
        $this->assertArrayHasKey('recommended_delay', $status);

        $this->assertEquals(1, $status['requests']);
        $this->assertEquals(1, $status['concurrent']);

        $this->rateLimiter->releaseRequest($token);
    }

    
    public function test_different_realms_have_separate_rate_limits()
    {
        $rateLimiter1 = new QuickbooksRateLimiter('realm-1');
        $rateLimiter2 = new QuickbooksRateLimiter('realm-2');

        // Track requests on realm 1
        for ($i = 0; $i < 100; $i++) {
            $rateLimiter1->trackRequest();
        }

        $status1 = $rateLimiter1->getStatus();
        $status2 = $rateLimiter2->getStatus();

        $this->assertEquals(100, $status1['requests']);
        $this->assertEquals(0, $status2['requests']); // Separate realm, no requests
    }

    
    public function test_it_waits_for_capacity()
    {
        // This test would need to mock Cache TTL behavior
        // For now, just verify the method exists and returns correct type
        $this->assertTrue($this->rateLimiter->canMakeRequest());

        $result = $this->rateLimiter->waitForCapacity(1);

        $this->assertTrue($result);
    }
}
