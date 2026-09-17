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

namespace App\Services\Quickbooks\Cdc;

use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\QuickbooksRateLimiter;

/**
 * Thin, self-contained wrapper around the Intuit SDK's Change Data Capture
 * endpoint.
 *
 * Deliberately does NOT touch SdkWrapper (which does not expose CDC): it calls
 * the raw DataService directly and re-uses the existing QuickbooksRateLimiter
 * to stay within the realm's request budget — same gate SdkWrapper::execute()
 * applies, replicated here without modifying it.
 */
class CdcChangeFetcher
{
    private const RATE_LIMIT_MAX_WAIT_SECONDS = 90;

    public function __construct(private QuickbooksService $service) {}

    /**
     * Fetch all entities of the given types changed since $changedSince.
     *
     * @param  array<int, string> $entities     QuickBooks entity names.
     * @param  string             $changedSince ISO8601 timestamp.
     * @return array<string, array<int, mixed>> Records keyed by QB entity name.
     *         Returns [] on error (logged) so a poll never hard-fails the job.
     */
    public function fetch(array $entities, string $changedSince): array
    {
        if ($entities === [] || !isset($this->service->sdk)) {
            return [];
        }

        $limiter = $this->rateLimiter();
        $request_token = null;

        if ($limiter) {
            if (!$limiter->waitForCapacity(self::RATE_LIMIT_MAX_WAIT_SECONDS)) {
                nlog('QB CDC: no rate-limit capacity, skipping poll');

                return [];
            }

            $request_token = $limiter->acquireRequest();
            $limiter->trackRequest();
        }

        try {
            $response = $this->service->sdk->CDC($entities, $changedSince);

            // The SDK returns null and records the fault when it does not throw.
            $error = $this->service->sdk->getLastError();

            if ($error) { //@phpstan-ignore-line
                nlog('QB CDC: fault => ' . (method_exists($error, 'getResponseBody') ? $error->getResponseBody() : 'unknown')); //@phpstan-ignore-line

                return [];
            }

            return $this->normalize($response->entities ?? []); //@phpstan-ignore-line
        } catch (\Throwable $e) {
            if ($limiter && QuickbooksRateLimiter::isRateLimitException($e)) {
                $limiter->enterBackoff(60);
            }

            nlog('QB CDC: fetch failed => ' . $e->getMessage());

            return [];
        } finally {
            if ($limiter && $request_token) {
                $limiter->releaseRequest($request_token);
            }
        }
    }

    private function rateLimiter(): ?QuickbooksRateLimiter
    {
        $realm = $this->service->company->quickbooks->realmID ?? null;

        return $realm ? new QuickbooksRateLimiter($realm) : null;
    }

    /**
     * Normalise each bucket to a list. CDC may hand back a single object, an
     * associative object, or null per entity type.
     *
     * @param  array<string, mixed> $entities
     * @return array<string, array<int, mixed>>
     */
    private function normalize(array $entities): array //@phpstan-ignore-line
    {
        $out = [];

        foreach ($entities as $name => $records) {
            if (empty($records)) {
                $out[$name] = [];

                continue;
            }

            $out[$name] = is_array($records)
                ? (array_is_list($records) ? $records : [$records])
                : [$records];
        }

        return $out;
    }
}
