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

namespace App\Utils;

use Illuminate\Support\Facades\Cache;

/**
 * Class EmailStats.
 */
class EmailStats
{
    public const EMAIL = 'email_';

    /**
     * Increments the counter for emails sent
     * for a company.
     * @param  string $company_key The company key
     * @return void
     */
    public static function inc($company_key)
    {
        Cache::increment("email_quota".self::EMAIL.$company_key);
    }

    /**
     * Returns the email sent count.
     *
     * @param  string $company_key The company key
     * @return int                 The number email sent so far 'today'
     */
    public static function count($company_key)
    {
        return Cache::get(self::EMAIL.$company_key);
    }

    /**
     * Clears the cache for the emails sent.
     *
     * @param  string $company_key The company key
     * @return void
     */
    public static function clear($company_key)
    {
        Cache::forget(self::EMAIL.$company_key);
    }

    /**
     * Iterates through a list of companies
     * and flushes the email sent data.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<\App\Models\Company> $companies The company key
     * @return void
     */
    public static function clearCompanies($companies)
    {
        $companies->each(function ($company) {
            /** @var \App\Models\Company $company */
            self::clear($company->company_key);
        });
    }
}
