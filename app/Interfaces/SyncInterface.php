<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Interfaces;

interface SyncInterface
{
    public function find(string $id): mixed;

    public function syncToNinja(array $records): void;

    public function syncToForeign(array $records): void;
}
