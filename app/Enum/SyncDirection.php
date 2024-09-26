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

namespace App\Enum;

enum SyncDirection: string
{
    case PUSH = 'push'; // only creates and updates records created by Invoice Ninja.
    case PULL = 'pull'; // creates and updates record from QB.
    case BIDIRECTIONAL = 'bidirectional'; // creates and updates records created by Invoice Ninja and from QB.
}
