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

        if (! $licenseKey) {
            return false;
        }

        try {
            $response = Http::timeout(15)
                ->get(config('ninja.hosted_ninja_url') . '/claim_license', [
                    'license_key' => $licenseKey,
                    'product_id' => 3,
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

                        nlog('White label license auto-renewed. New expiry: ' . $expires->format('Y-m-d'));

                        return true;
                    }
                }
            }

            return false;
        } catch (ConnectionException $e) {
            nlog('White label renewal check failed - network error: ' . $e->getMessage());

            return null;
        } catch (\Exception $e) {
            nlog('White label renewal check failed: ' . $e->getMessage());

            return null;
        }
    }
}
