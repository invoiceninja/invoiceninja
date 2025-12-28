<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

use App\Enum\SyncDirection;

/**
 * QuickbooksSyncMap.
 */
class QuickbooksSyncMap
{
    public SyncDirection $direction = SyncDirection::BIDIRECTIONAL;

    public function __construct(array $attributes = [])
    {
        $this->direction = isset($attributes['direction'])
           ? SyncDirection::from($attributes['direction'])
           : SyncDirection::BIDIRECTIONAL;

    }
}
