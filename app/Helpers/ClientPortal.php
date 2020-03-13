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


/**
 * Check if passed page is currently active.
 *
 * @param $page
 * @param bool $boolean
 * @return bool
 */
function isActive($page, bool $boolean = false) {

    $current_page = Route::currentRouteName();

    if($page == $current_page && $boolean)
        return true;

    if($page == $current_page)
        return 'active-page';

    return false;
}
