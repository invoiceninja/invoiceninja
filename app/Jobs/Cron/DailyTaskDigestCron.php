<?php

/**
 * Invoice Ninja (https://www.invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://www.invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Models\CompanyUser;
use App\Models\Timezone;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DailyTaskDigestCron implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const DELIVERY_HOUR = 8;

    private const WINDOW_HOURS = 4;

    public int $tries = 1;

    public function handle(): void
    {
        $started_at = microtime(true);
        $window_end_utc = CarbonImmutable::now('UTC')->startOfHour();
        $due_timezone_ids = $this->getDueTimezoneIds($window_end_utc);
        $original_database = config('database.default');

        $databases = config('ninja.db.multi_db_enabled')
            ? MultiDB::getDbs()
            : [$original_database];

        $eligible_company_users = 0;

        try {
            foreach ($databases as $database) {
                MultiDB::setDB($database);

                $eligible_company_users += $this->recipientQuery($due_timezone_ids)->count();
            }
        } finally {
            MultiDB::setDB($original_database);
        }

        nlog(sprintf(
            'DailyTaskDigestCron:: Found %d eligible company users in %.2f seconds',
            $eligible_company_users,
            microtime(true) - $started_at
        ));
    }

    /**
     * @return array<int, string>
     */
    private function getDueTimezoneIds(CarbonImmutable $window_end_utc): array
    {
        $window_start_utc = $window_end_utc->subHours(self::WINDOW_HOURS);

        /** @var \Illuminate\Support\Collection<int, Timezone> $timezones */
        $timezones = app('timezones');

        return $timezones
            ->filter(function (Timezone $timezone) use ($window_start_utc, $window_end_utc): bool {
                try {
                    $local_now = $window_end_utc->setTimezone($timezone->name);
                    $local_target = $local_now->setTime(self::DELIVERY_HOUR, 0);
                } catch (\Exception) {
                    return false;
                }

                if ($local_target->greaterThan($local_now)) {
                    $local_target = $local_target->subDay();
                }

                $target_utc = $local_target->setTimezone('UTC');

                return $target_utc->greaterThan($window_start_utc)
                    && $target_utc->lessThanOrEqualTo($window_end_utc);
            })
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $due_timezone_ids
     * @return Builder<CompanyUser>
     */
    private function recipientQuery(array $due_timezone_ids): Builder
    {
        return CompanyUser::query()
            ->whereHas('company', function (Builder $query) use ($due_timezone_ids): void {
                $query
                    ->where('is_disabled', false)
                    ->whereIn('settings->timezone_id', $due_timezone_ids);
            })
            ->whereHas('user', function (Builder $query): void {
                $query
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at');
            })
            ->where('is_locked', false)
            ->where('notifications', 'like', '%task_daily_digest%');
    }
}
