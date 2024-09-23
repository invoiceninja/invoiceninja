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

enum SyncDirection: string
{
    case PUSH = 'push';
    case PULL = 'pull';
    case BIDIRECTIONAL = 'bidirectional';
}

/**
 * QuickbooksSyncMap.
 */
class QuickbooksSyncMap
{
    public bool $sync = true;

    public bool $update_record = true;

    public SyncDirection $direction = SyncDirection::BIDIRECTIONAL; 

    public function __construct(array $attributes = [])
    {
        $this->sync = $attributes['sync'] ?? true;
        $this->update_record = $attributes['update_record'] ?? true;
        $this->direction = isset($attributes['direction'])
           ? SyncDirection::from($attributes['direction'])
           : SyncDirection::BIDIRECTIONAL;

    }
}

