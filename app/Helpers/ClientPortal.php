<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
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
function isActive($page, bool $boolean = false)
{
    $current_page = Route::currentRouteName();

    if ($page == $current_page && $boolean) {
        return true;
    }

    if ($page == $current_page) {
        return 'active-page';
    }

    return false;
}

/**
 * New render method that works with themes.
 *
 * @param string $path
 * @param array $options
 * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
 */
function render(string $path, array $options = [])
{
    $theme = array_key_exists('theme', $options) ? $options['theme'] : 'ninja2020';

    if (array_key_exists('root', $options)) {
        return view(
            sprintf('%s.%s.%s', $options['root'], $theme, $path),
            $options
        );
    }

    return view("portal.$theme.$path", $options);
}
