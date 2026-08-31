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
        $startedAt = microtime(true);
        $windowEndUtc = CarbonImmutable::now('UTC')->startOfHour();
        $dueTimezoneIds = $this->getDueTimezoneIds($windowEndUtc);
        $originalDatabase = config('database.default');
        $databases = config('ninja.db.multi_db_enabled')
            ? MultiDB::getDbs()
            : [$originalDatabase];
        $eligibleCompanyUsers = 0;

        try {
            foreach ($databases as $database) {
                MultiDB::setDB($database);

                $eligibleCompanyUsers += $this->recipientQuery($dueTimezoneIds)->count();
            }
        } finally {
            MultiDB::setDB($originalDatabase);
        }

        nlog(sprintf(
            'DailyTaskDigestCron:: Found %d eligible company users in %.2f seconds',
            $eligibleCompanyUsers,
            microtime(true) - $startedAt
        ));
    }

    /**
     * @return array<int, string>
     */
    private function getDueTimezoneIds(CarbonImmutable $windowEndUtc): array
    {
        $windowStartUtc = $windowEndUtc->subHours(self::WINDOW_HOURS);

        return app('timezones')
            ->filter(function (Timezone $timezone) use ($windowStartUtc, $windowEndUtc): bool {
                try {
                    $localNow = $windowEndUtc->setTimezone($timezone->name);
                    $localTarget = $localNow->setTime(self::DELIVERY_HOUR, 0);
                } catch (\Exception) {
                    return false;
                }

                if ($localTarget->greaterThan($localNow)) {
                    $localTarget = $localTarget->subDay();
                }

                $targetUtc = $localTarget->setTimezone('UTC');

                return $targetUtc->greaterThan($windowStartUtc)
                    && $targetUtc->lessThanOrEqualTo($windowEndUtc);
            })
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $dueTimezoneIds
     * @return Builder<CompanyUser>
     */
    private function recipientQuery(array $dueTimezoneIds): Builder
    {
        return CompanyUser::query()
            ->whereHas('company', function (Builder $query) use ($dueTimezoneIds): void {
                $query
                    ->where('is_disabled', false)
                    ->whereIn('settings->timezone_id', $dueTimezoneIds);
            })
            ->whereHas('user', function (Builder $query): void {
                $query
                    ->where('is_deleted', false)
                    ->whereNull('deleted_at');
            })
            ->where('is_locked', false)
            ->where('notifications', 'like', '%"task\_daily\_digest"%');
    }
}
