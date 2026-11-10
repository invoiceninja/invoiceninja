<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use App\Utils\Ninja;

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

    if (! config('ninja.expanded_logging')) {
        return;
    }

    if (gettype($output) == 'object') {
        $output = print_r($output, true);
    }

    // $trace = debug_backtrace();

    if (Ninja::isHosted()) {
        try {
            info($output);
        } catch (\Exception $e) {
        }
    } else {
        \Illuminate\Support\Facades\Log::channel('invoiceninja')->info($output, $context);
    }

    $output = null;
    $context = null;
}


function nrlog($output, $context = []): void
{
    if (! config('ninja.expanded_logging')) {
        return;
    }

    if (gettype($output) == 'object') {
        $output = print_r($output, true);
    }

    // $trace = debug_backtrace();

    if (Ninja::isHosted()) {
        try {
            info($output);
        } catch (\Exception $e) {
        }
    } else {
        \Illuminate\Support\Facades\Log::channel('invoiceninja-reminders')->info($output, $context);
    }

    $output = null;
    $context = null;
}

/**
 * Strip markup and decode entities for text/plain email bodies.
 */
function plain_email_text(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $value = str_replace(['<br>', '<br/>', '<br />'], "\n", $value);
    $value = strip_tags($value);

    return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
