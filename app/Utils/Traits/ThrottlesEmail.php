<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils\Traits;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Class ThrottlesEmail.
 */
trait ThrottlesEmail
{
    public function getDailyEmailLimit(Company $company)
    {
        $limit = config('ninja.daily_email_limit');

        $limit += $company->created_at->diffInMonths() * 100;

        return min($limit, 5000);
    }

    public function isThrottled(Company $company)
    {
        $key = $company->company_key;

        // http://stackoverflow.com/questions/1375501/how-do-i-throttle-my-sites-api-users
        $day = 60 * 60 * 24;
        $day_limit = $this->getDailyEmailLimit($company);
        $day_throttle = Cache::get("email_day_throttle:{$key}", null);
        $last_api_request = Cache::get("last_email_request:{$key}", 0);
        $last_api_diff = time() - $last_api_request;

        if (is_null($day_throttle)) {
            $new_day_throttle = 0;
        } else {
            $new_day_throttle = $day_throttle - $last_api_diff;
            $new_day_throttle = $new_day_throttle < 0 ? 0 : $new_day_throttle;
            $new_day_throttle += $day / $day_limit;
            $day_hits_remaining = floor(($day - $new_day_throttle) * $day_limit / $day);
            $day_hits_remaining = $day_hits_remaining >= 0 ? $day_hits_remaining : 0;
        }

        Cache::put("email_day_throttle:{$key}", $new_day_throttle, 60);
        Cache::put("last_email_request:{$key}", time(), 60);

        if ($new_day_throttle > $day) {
            $error_email = config('ninja.error_email');
            if ($error_email && ! Cache::get("throttle_notified:{$key}")) {
                Mail::raw('Account Throttle: '.$company->company_key, function ($message) use ($error_email, $company) {
                    $message->to($error_email)
                            ->from(config('ninja.contact.email'))
                            ->subject('Email throttle triggered for company '.$company->id);
                });
            }
            Cache::put("throttle_notified:{$key}", true, 60 * 24);

            return true;
        }

        return false;
    }
}
