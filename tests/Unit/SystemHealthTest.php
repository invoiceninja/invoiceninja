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
use Tests\TestCase;

/**
 *
 *   App\Utils\SystemHealth
 */
class SystemHealthTest extends TestCase
{
    private ?string $originalLog = null;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = base_path('storage/logs/laravel.log');
        $this->originalLog = file_exists($this->logPath) ? file_get_contents($this->logPath) : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalLog === null) {
            @unlink($this->logPath);
        } else {
            file_put_contents($this->logPath, $this->originalLog);
        }

        parent::tearDown();
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

    public function testLastErrorReturnsEmptyWhenLogFileIsEmpty()
    {
        file_put_contents($this->logPath, '');

        $this->assertSame('', SystemHealth::lastError());
    }

    public function testLastErrorReturnsMostRecentError()
    {
        file_put_contents($this->logPath, implode("\n", [
            '[2026-09-10 18:00:00] production.INFO: All good',
            '[2026-09-10 18:01:00] production.ERROR: First error',
            '[2026-09-10 18:02:00] production.ERROR: Latest error',
            '',
        ]));

        $this->assertSame('[2026-09-10 18:02:00] production.ERROR: Latest error' . "\n", SystemHealth::lastError());
    }
}
