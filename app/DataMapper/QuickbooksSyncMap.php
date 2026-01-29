<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
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
    public SyncDirection $direction = SyncDirection::NONE;

    public function __construct(array $attributes = [])
    {
        $this->direction = isset($attributes['direction'])
           ? SyncDirection::from($attributes['direction'])
           : SyncDirection::NONE;
    }

    public function toArray(): array
    {
        // Ensure direction is always returned as a string value, not the enum object
        $directionValue = $this->direction instanceof \App\Enum\SyncDirection 
            ? $this->direction->value 
            : (string) $this->direction;
            
        return [
            'direction' => $directionValue,
        ];
    }
}
