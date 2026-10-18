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

use App\Models\Client;

/**
 * Creates Ninja clients for QuickBooks Customers that appeared in the CDC
 * window and do not yet exist locally.
 */
class CdcCustomerCreator extends AbstractCdcCreator
{
    public function qbEntities(): array
    {
        return ['Customer'];
    }

    public function ninjaEntity(): string
    {
        return 'client';
    }

    protected function modelClass(): string
    {
        return Client::class;
    }

    protected function persist(array $records): void
    {
        // $records are pre-filtered to new-only, so syncToNinja acts as a creator.
        $this->service->client->syncToNinja($records);
    }
}
