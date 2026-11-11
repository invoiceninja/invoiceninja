<?php

namespace Tests\Unit\Services\Quickbooks;

use App\DataMapper\QuickbooksSettings;
use App\Models\Company;
use App\Services\Quickbooks\Cdc\CdcSyncResult;
use App\Services\Quickbooks\Cdc\CdcWatermarkStore;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CdcSupportTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function testWatermarkUsesDefaultSeedForFirstRun(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');
        $store = new CdcWatermarkStore($this->company('realm-a'));
        $store->forget();

        $resolved = $store->resolveChangedSince();

        $this->assertSame('2026-09-02T12:00:00+00:00', $resolved['since']);
        $this->assertFalse($resolved['requires_full_sync']);
    }

    public function testWatermarkPersistsPerCompanyAndRealm(): void
    {
        $first = new CdcWatermarkStore($this->company('realm-a', 10));
        $second = new CdcWatermarkStore($this->company('realm-b', 10));
        $first->forget();
        $second->forget();

        $first->put('2026-09-01T00:00:00+00:00');
        $second->put('2026-09-02T00:00:00+00:00');

        $this->assertSame('2026-09-01T00:00:00+00:00', $first->get());
        $this->assertSame('2026-09-02T00:00:00+00:00', $second->get());

        $first->forget();

        $this->assertNull($first->get());
        $this->assertSame('2026-09-02T00:00:00+00:00', $second->get());
    }

    public function testExpiredWatermarkIsClampedAndRequiresFullSync(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');
        $store = new CdcWatermarkStore($this->company('realm-a'));

        $resolved = $store->resolveChangedSince('2026-01-01T00:00:00+00:00');

        $this->assertSame('2026-08-05T12:00:00+00:00', $resolved['since']);
        $this->assertTrue($resolved['requires_full_sync']);
    }

    public function testExplicitRecentOverrideTakesPriorityOverStoredWatermark(): void
    {
        Carbon::setTestNow('2026-09-03 12:00:00 UTC');
        $store = new CdcWatermarkStore($this->company('realm-a'));
        $store->put('2026-09-01T00:00:00+00:00');

        $resolved = $store->resolveChangedSince('2026-09-03T10:00:00+00:00');

        $this->assertSame('2026-09-03T10:00:00+00:00', $resolved['since']);
        $this->assertFalse($resolved['requires_full_sync']);
    }

    public function testSyncResultSummarizesCreatedRecordsAndSkipReason(): void
    {
        $result = new CdcSyncResult(
            counts: ['client' => 2, 'invoice' => 3],
            since: '2026-09-03T10:00:00+00:00',
            requires_full_sync: true,
        );

        $this->assertSame(5, $result->created());
        $this->assertSame([
            'created' => 5,
            'counts' => ['client' => 2, 'invoice' => 3],
            'since' => '2026-09-03T10:00:00+00:00',
            'requires_full_sync' => true,
            'skipped' => false,
            'reason' => null,
        ], $result->toArray());

        $skipped = CdcSyncResult::skipped('quickbooks_not_configured');

        $this->assertTrue($skipped->skipped);
        $this->assertSame('quickbooks_not_configured', $skipped->reason);
        $this->assertSame(0, $skipped->created());
    }

    private function company(string $realm, int $id = 1): Company
    {
        $company = new Company();
        $company->id = $id;
        $company->db = 'test-db';
        $company->quickbooks = new QuickbooksSettings([
            'realmID' => $realm,
        ]);

        return $company;
    }
}
