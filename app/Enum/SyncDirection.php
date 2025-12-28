<?php

/**
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
