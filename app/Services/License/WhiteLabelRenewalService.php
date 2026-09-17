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

namespace App\Services\License;

use App\Models\Account;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhiteLabelRenewalService
{
    /**
     * Check if an expired white label license has been renewed on the license server.
     *
     * @return bool|null true = renewed, false = not renewed, null = inconclusive (network error)
     */
    public function checkAndRenew(Account $account): ?bool
    {
        $licenseKey = config('ninja.license_key');
        $url = rtrim((string) config('ninja.hosted_ninja_url'), '/') . '/claim_license';

        if (! $licenseKey) {
            Log::warning('White label license: renewal skipped because LICENSE_KEY is not set', [
                'step' => 'license_key',
                'license_server_url' => $url,
                'reachable' => null,
                'status_code' => null,
            ]);

            return false;
        }

        try {
            $response = Http::timeout(15)
                ->connectTimeout(10)
                ->get($url, [
                    'license_key' => $licenseKey,
                    'product_id' => 3,
                ]);
        } catch (ConnectionException $e) {
            Log::error('White label license: renewal check failed - license server unreachable', [
                'step' => 'connect',
                'license_server_url' => $url,
                'reachable' => false,
                'status_code' => null,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('White label license: renewal check failed', [
                'step' => 'connect',
                'license_server_url' => $url,
                'reachable' => false,
                'status_code' => null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        Log::info('White label license: renewal check response', [
            'step' => 'http_response',
            'license_server_url' => $url,
            'reachable' => true,
            'status_code' => $response->status(),
        ]);

        if ($response->successful()) {
            $payload = $response->json();

            if (is_array($payload) && isset($payload['expires'])) {
                $expires = Carbon::parse($payload['expires']);

                if ($expires->gt(now())) {
                    $account->plan_term = Account::PLAN_TERM_YEARLY;
                    $account->plan_expires = $expires->format('Y-m-d');
                    $account->plan = Account::PLAN_WHITE_LABEL;
                    $account->saveQuietly();

                    Log::info('White label license: auto-renewed', [
                        'step' => 'apply',
                        'license_server_url' => $url,
                        'reachable' => true,
                        'status_code' => $response->status(),
                        'expires' => $expires->format('Y-m-d'),
                    ]);

                    return true;
                }
            }
        }

        return false;
    }
}
