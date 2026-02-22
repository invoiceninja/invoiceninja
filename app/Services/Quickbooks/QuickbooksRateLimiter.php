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

namespace App\Services\Quickbooks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * QuickBooks API Rate Limiter
 *
 * Tracks and enforces QuickBooks API rate limits:
 * - 500 requests per minute per company (realm)
 * - 10 concurrent requests per OAuth token
 *
 * Uses Redis for distributed rate limiting across multiple workers.
 */
class QuickbooksRateLimiter
{
    /**
     * QuickBooks rate limits
     */
    private const REQUESTS_PER_MINUTE = 500;
    private const CONCURRENT_LIMIT = 10;
    private const WINDOW_SECONDS = 60;

    /**
     * Safety margin (use 80% of limit)
     */
    private const SAFETY_FACTOR = 0.8;

    /**
     * Cache key prefixes
     */
    private const PREFIX_REQUESTS = 'qb_rate_limit';
    private const PREFIX_CONCURRENT = 'qb_concurrent';
    private const PREFIX_BACKOFF = 'qb_backoff';

    public function __construct(private string $realmId)
    {
    }

    /**
     * Check if we can make a request without hitting rate limits
     *
     * @return bool
     */
    public function canMakeRequest(): bool
    {
        // Check if we're in backoff mode due to 429 error
        if ($this->isInBackoff()) {
            return false;
        }

        // Check requests per minute limit
        $requestCount = $this->getRequestCount();
        $maxRequests = (int) (self::REQUESTS_PER_MINUTE * self::SAFETY_FACTOR);

        if ($requestCount >= $maxRequests) {
            nlog("QB rate limit: {$requestCount}/{$maxRequests} requests used in current window");
            return false;
        }

        // Check concurrent requests limit
        $concurrentCount = $this->getConcurrentCount();
        $maxConcurrent = (int) (self::CONCURRENT_LIMIT * self::SAFETY_FACTOR);

        if ($concurrentCount >= $maxConcurrent) {
            nlog("QB concurrent limit: {$concurrentCount}/{$maxConcurrent} concurrent requests");
            return false;
        }

        return true;
    }

    /**
     * Wait until we can make a request (blocks)
     *
     * @param int $maxWaitSeconds Maximum seconds to wait
     * @return bool True if can proceed, false if timed out
     */
    public function waitForCapacity(int $maxWaitSeconds = 60): bool
    {
        $waited = 0;

        while (!$this->canMakeRequest() && $waited < $maxWaitSeconds) {
            sleep(1);
            $waited++;
        }

        return $this->canMakeRequest();
    }

    /**
     * Get recommended delay in seconds before next request
     *
     * @return int Seconds to wait
     */
    public function getRecommendedDelay(): int
    {
        if ($this->isInBackoff()) {
            return $this->getBackoffSeconds();
        }

        $requestCount = $this->getRequestCount();
        $maxRequests = (int) (self::REQUESTS_PER_MINUTE * self::SAFETY_FACTOR);

        // If near limit, calculate delay to spread requests evenly
        if ($requestCount > $maxRequests * 0.7) {
            $remainingRequests = $maxRequests - $requestCount;
            $remainingTime = $this->getRemainingWindowTime();

            if ($remainingRequests > 0) {
                return (int) ceil($remainingTime / $remainingRequests);
            }

            return $remainingTime;
        }

        // Normal operation: minimal delay
        return 0;
    }

    /**
     * Track a request being made
     *
     * @return void
     */
    public function trackRequest(): void
    {
        $key = $this->getRequestCountKey();

        // Increment counter with 60-second expiry
        Cache::increment($key, 1);
        Cache::put($key, Cache::get($key, 0), self::WINDOW_SECONDS);
    }

    /**
     * Track start of a concurrent request
     *
     * @return string Token to pass to releaseRequest()
     */
    public function acquireRequest(): string
    {
        $token = uniqid('qb_req_', true);
        $key = $this->getConcurrentKey();

        // Add to concurrent set
        Cache::remember($key, self::WINDOW_SECONDS, fn() => []);
        $concurrent = Cache::get($key, []);
        $concurrent[$token] = time();
        Cache::put($key, $concurrent, self::WINDOW_SECONDS);

        return $token;
    }

    /**
     * Track completion of a concurrent request
     *
     * @param string $token Token from acquireRequest()
     * @return void
     */
    public function releaseRequest(string $token): void
    {
        $key = $this->getConcurrentKey();

        $concurrent = Cache::get($key, []);
        unset($concurrent[$token]);
        Cache::put($key, $concurrent, self::WINDOW_SECONDS);
    }

    /**
     * Enter backoff mode due to 429 rate limit response
     *
     * @param int $backoffSeconds Seconds to backoff (exponential)
     * @return void
     */
    public function enterBackoff(int $backoffSeconds = 60): void
    {
        $key = $this->getBackoffKey();
        Cache::put($key, time() + $backoffSeconds, $backoffSeconds);

        nlog("QB rate limit: Entering backoff mode for {$backoffSeconds} seconds");
    }

    /**
     * Check if currently in backoff mode
     *
     * @return bool
     */
    public function isInBackoff(): bool
    {
        $key = $this->getBackoffKey();
        $backoffUntil = Cache::get($key);

        return $backoffUntil && $backoffUntil > time();
    }

    /**
     * Get remaining backoff seconds
     *
     * @return int
     */
    public function getBackoffSeconds(): int
    {
        if (!$this->isInBackoff()) {
            return 0;
        }

        $key = $this->getBackoffKey();
        $backoffUntil = Cache::get($key);

        return max(0, $backoffUntil - time());
    }

    /**
     * Get current request count in the window
     *
     * @return int
     */
    private function getRequestCount(): int
    {
        return (int) Cache::get($this->getRequestCountKey(), 0);
    }

    /**
     * Get current concurrent request count
     *
     * @return int
     */
    private function getConcurrentCount(): int
    {
        $concurrent = Cache::get($this->getConcurrentKey(), []);

        // Clean up stale entries (older than 5 minutes)
        $now = time();
        $concurrent = array_filter($concurrent, fn($timestamp) => ($now - $timestamp) < 300);

        return count($concurrent);
    }

    /**
     * Get remaining time in current rate limit window
     *
     * @return int Seconds remaining
     */
    private function getRemainingWindowTime(): int
    {
        // For simplicity and compatibility, return the default window size
        // In a real scenario with more precise tracking, we'd store the window start time
        // For rate limiting purposes, a conservative estimate (full window) is safer
        return self::WINDOW_SECONDS;
    }

    /**
     * Get cache key for request count
     *
     * @return string
     */
    private function getRequestCountKey(): string
    {
        return self::PREFIX_REQUESTS . ':' . $this->realmId;
    }

    /**
     * Get cache key for concurrent requests
     *
     * @return string
     */
    private function getConcurrentKey(): string
    {
        return self::PREFIX_CONCURRENT . ':' . $this->realmId;
    }

    /**
     * Get cache key for backoff mode
     *
     * @return string
     */
    private function getBackoffKey(): string
    {
        return self::PREFIX_BACKOFF . ':' . $this->realmId;
    }

    /**
     * Reset all rate limit tracking (for testing)
     *
     * @return void
     */
    public function reset(): void
    {
        Cache::forget($this->getRequestCountKey());
        Cache::forget($this->getConcurrentKey());
        Cache::forget($this->getBackoffKey());
    }

    /**
     * Get current rate limit status
     *
     * @return array{requests: int, concurrent: int, in_backoff: bool, backoff_seconds: int}
     */
    public function getStatus(): array
    {
        return [
            'requests' => $this->getRequestCount(),
            'max_requests' => (int) (self::REQUESTS_PER_MINUTE * self::SAFETY_FACTOR),
            'concurrent' => $this->getConcurrentCount(),
            'max_concurrent' => (int) (self::CONCURRENT_LIMIT * self::SAFETY_FACTOR),
            'in_backoff' => $this->isInBackoff(),
            'backoff_seconds' => $this->getBackoffSeconds(),
            'can_make_request' => $this->canMakeRequest(),
            'recommended_delay' => $this->getRecommendedDelay(),
        ];
    }
}
