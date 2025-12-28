<?php

/**
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
