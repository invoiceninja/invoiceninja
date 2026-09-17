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

namespace App\Services\Quickbooks\Cdc\Creators;

/**
 * Contract for a CDC entity creator.
 *
 * A creator is responsible for a single Ninja entity type. It receives the raw
 * QuickBooks records returned by a Change Data Capture (CDC) poll and creates
 * ONLY the records that do not yet exist in Invoice Ninja. Existing records are
 * never touched on this pass.
 */
interface CdcEntityCreator
{
    /**
     * QuickBooks entity names this creator consumes from the CDC response.
     * e.g. ['Invoice', 'SalesReceipt'].
     *
     * @return array<int, string>
     */
    public function qbEntities(): array;

    /**
     * The Ninja sync-direction key used to gate this entity (see
     * QuickbooksService::syncable()). e.g. 'invoice'.
     */
    public function ninjaEntity(): string;

    /**
     * Create any records in $records that are not already present in Ninja.
     *
     * @param  array<int, mixed> $records Raw QuickBooks entity objects.
     * @return int Number of records created.
     */
    public function createNew(array $records): int;
}
