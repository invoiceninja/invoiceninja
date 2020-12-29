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
 * Simple helper function that will log into "invoiceninja.log" file
 * only when extended logging is enabled.
 *
 * @param mixed $output
 * @param array $context
 *
 * @return void
 */
function nlog($output, $context = []): void
{
    if (config('ninja.expanded_logging')) {
        if (gettype($output) == 'object') {
            $output = print_r($output, 1);
        }

        \Illuminate\Support\Facades\Log::channel('invoiceninja')->info($output, $context);
    }
}
