<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

use App\Utils\Traits\MakesDates;

/**
 * Example proxy class to inject
 * possible trait calls and functions.
 *
 * Note: Shouldn't be called outside of this file.
 */
class ClientPortalHelpers
{
    use MakesDates;
}

/**
 * Check if passed page is currently active.
 *
 * @param $page
 * @param bool $boolean
 * @return bool
 */
function isActive($page, bool $boolean = false)
{
    $current_page = Route::currentRouteName();

    if ($page == $current_page && $boolean)
        return true;

    if ($page == $current_page)
        return 'active-page';

    return false;
}

/**
 * Proxy method/helper to formatDate from MakesDate.
 *
 * @param $date
 * @param string $format
 * @return string
 */
function format_date($date, string $format): string
{
    return (new ClientPortalHelpers())->formatDate($date, $format);
}

/**
 * Proxy method/helper to formatDateTimestamp from MakesDate.
 *
 * @param $timestamp
 * @param string $format
 * @return string
 */
function format_date_timestamp($timestamp, string $format): string
{
    return (new ClientPortalHelpers())->formatDateTimestamp($timestamp, $format);
}
