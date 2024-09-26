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
