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

namespace Tests\Unit\DataMapper;

use App\DataMapper\InvoiceSync;
use App\Enum\InvoiceQbStatus;
use PHPUnit\Framework\TestCase;

class InvoiceSyncTest extends TestCase
{
    public function testDefaultsToSyncableWhenStatusEmpty(): void
    {
        $sync = new InvoiceSync();

        $this->assertSame(InvoiceQbStatus::Syncable, $sync->status());
        $this->assertFalse($sync->isLinked());
        $this->assertSame('', $sync->qb_status);
        $this->assertSame('', $sync->qb_status_message);
    }

    public function testMarkSyncedSetsIdTokenAndClearsMessage(): void
    {
        $sync = new InvoiceSync();
        $sync->markLinkable('pending link');
        $sync->markSynced('QB-123', '5');

        $this->assertTrue($sync->isLinked());
        $this->assertSame('QB-123', $sync->qb_id);
        $this->assertSame('5', $sync->qb_sync_token);
        $this->assertSame(InvoiceQbStatus::Synced, $sync->status());
        $this->assertSame(InvoiceQbStatus::Synced->value, $sync->qb_status);
        $this->assertSame('', $sync->qb_status_message);
    }

    public function testMarkSyncedCanPreserveStatusMessage(): void
    {
        $sync = new InvoiceSync(qb_status_message: 'QuickBooks rejected DisplayName.');

        $sync->markSynced('QB-123', '6', false);

        $this->assertSame('QB-123', $sync->qb_id);
        $this->assertSame('6', $sync->qb_sync_token);
        $this->assertSame(InvoiceQbStatus::Synced, $sync->status());
        $this->assertSame('QuickBooks rejected DisplayName.', $sync->qb_status_message);
    }

    public function testMarkSyncableCanPreserveStatusMessage(): void
    {
        $sync = new InvoiceSync(qb_status_message: 'Previous push failure.');

        $sync->markSyncable(false);

        $this->assertSame(InvoiceQbStatus::Syncable, $sync->status());
        $this->assertSame('Previous push failure.', $sync->qb_status_message);
    }

    public function testHydrateDoesNotInferStatusFromQbId(): void
    {
        $sync = InvoiceSync::fromArray([
            'qb_id' => '999',
            'qb_status' => InvoiceQbStatus::Syncable->value,
        ]);

        $this->assertSame('999', $sync->qb_id);
        $this->assertSame(InvoiceQbStatus::Syncable->value, $sync->qb_status);
        $this->assertSame(InvoiceQbStatus::Syncable, $sync->status());
    }

    public function testEmptyStatusWithQbIdDoesNotAssumeSynced(): void
    {
        $sync = InvoiceSync::fromArray([
            'qb_id' => '999',
            'qb_status' => '',
        ]);

        $this->assertSame('999', $sync->qb_id);
        $this->assertSame('', $sync->qb_status);
        // status() falls back to syncable only as enum default when unset — not because of qb_id.
        $this->assertSame(InvoiceQbStatus::Syncable, $sync->status());
    }

    public function testMarkLinkableIsPreservedWhenUnlinked(): void
    {
        $sync = InvoiceSync::fromArray([
            'qb_id' => '',
            'qb_status' => InvoiceQbStatus::Linkable->value,
            'qb_status_message' => 'match found',
        ]);

        $this->assertSame(InvoiceQbStatus::Linkable, $sync->status());
        $this->assertSame('match found', $sync->qb_status_message);
    }

    public function testPushFailureOnlyUpdatesMessage(): void
    {
        $sync = InvoiceSync::fromArray([
            'qb_id' => '',
            'qb_status' => InvoiceQbStatus::Linkable->value,
            'qb_status_message' => 'match found',
        ]);

        $sync->markPushFailure('QuickBooks request failed');

        $this->assertSame('', $sync->qb_id);
        $this->assertSame(InvoiceQbStatus::Linkable->value, $sync->qb_status);
        $this->assertSame('QuickBooks request failed', $sync->qb_status_message);
    }

    public function testMarkDataMismatchSetsStatusAndMessage(): void
    {
        $sync = new InvoiceSync();

        $sync->markDataMismatch('QuickBooks data differs.');

        $this->assertSame(InvoiceQbStatus::DataMismatch, $sync->status());
        $this->assertSame('QuickBooks data differs.', $sync->qb_status_message);
    }
}
