<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use App\Utils\SystemHealth;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 *
 *   App\Utils\SystemHealth
 */
class SystemHealthTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testVariables()
    {
        $results = SystemHealth::check();

        $this->assertTrue(is_array($results));

        $this->assertTrue(count($results) > 1);

        $this->assertTrue((bool) $results['system_health']);

        // $this->assertTrue($results['extensions'][0]['mysqli']);
        $this->assertTrue($results['extensions'][0]['gd']);
        $this->assertTrue($results['extensions'][1]['curl']);
        $this->assertTrue($results['extensions'][2]['zip']);
    }

    public function testQueueDataIgnoresFailedJobsWhenFailedDriverIsNull(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Historical failure',
            'failed_at' => now()->addYear(),
        ]);

        config(['queue.failed.driver' => null]);
        app()->forgetInstance('queue.failer');

        $queue_data = SystemHealth::check(true, false)['queue_data'];

        $this->assertSame(0, $queue_data['failed']);
        $this->assertSame('', $queue_data['last_error']);

        config(['queue.failed.driver' => 'database-uuids']);
        app()->forgetInstance('queue.failer');

        $queue_data = SystemHealth::check(true, false)['queue_data'];

        $this->assertGreaterThan(0, $queue_data['failed']);
        $this->assertSame('Historical failure', $queue_data['last_error']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function errorLevelProvider(): array
    {
        return [
            'error' => ['ERROR'],
            'critical' => ['CRITICAL'],
            'alert' => ['ALERT'],
            'emergency' => ['EMERGENCY'],
        ];
    }

    #[DataProvider('errorLevelProvider')]
    public function testLastErrorMatchesErrorAndHigherSeverityLevels(string $level): void
    {
        $logLine = "[2026-08-06 13:25:03] production.{$level}: Something failed\n";

        $this->assertSame($logLine, $this->lastErrorFrom($logLine));
    }

    public function testLastErrorReturnsTheLatestMatchingEntry(): void
    {
        $latestError = "[2026-08-06 13:27:03] production.CRITICAL: Latest failure\n";
        $logContents = "[2026-08-06 13:25:03] production.ERROR: Earlier failure\n"
            . "[2026-08-06 13:26:03] production.WARNING: Warning\n"
            . $latestError;

        $this->assertSame($latestError, $this->lastErrorFrom($logContents));
    }

    public function testLastErrorIgnoresSeverityTextOutsideTheLogPrefix(): void
    {
        $logContents = "[2026-08-06 13:25:03] production.INFO: Upstream returned .ERROR: text\n"
            . "#0 /var/www/app/Example.php .CRITICAL: stack trace text\n";

        $this->assertSame('', $this->lastErrorFrom($logContents));
    }

    private function lastErrorFrom(string $logContents): string
    {
        $originalBasePath = app()->basePath();
        $temporaryBasePath = sys_get_temp_dir() . '/invoice-ninja-system-health-' . bin2hex(random_bytes(8));

        try {
            File::ensureDirectoryExists($temporaryBasePath . '/storage/logs');
            File::put($temporaryBasePath . '/storage/logs/laravel.log', $logContents);
            app()->setBasePath($temporaryBasePath);

            return SystemHealth::lastError();
        } finally {
            app()->setBasePath($originalBasePath);
            File::deleteDirectory($temporaryBasePath);
        }
    }
}
