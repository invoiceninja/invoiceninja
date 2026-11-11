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

namespace App\Services\Quickbooks;

class QuickbooksDisplayName
{
    public static function sanitize(string $name, int $max_length = 100): string
    {
        $name = str_replace(
            [':', '"', "'", '&', '<', '>', '\\', '/', '?', '*', '|'],
            ['-', '', '', ' and ', '', '', '', ' ', '', '', ''],
            $name
        );
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);
        $name = trim($name, '-');

        if ($name === '') {
            $name = 'Customer';
        }

        return mb_substr($name, 0, $max_length);
    }

    public static function unique(string $name, string $suffix = ' (C)'): string
    {
        $base = self::sanitize($name, 100 - mb_strlen($suffix));

        return self::sanitize($base . $suffix);
    }

    public static function conservative(string $name, int $max_length = 100): string
    {
        $name = self::sanitize($name, 400);
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name);

        if ($name === '') {
            $name = 'Customer';
        }

        return mb_substr($name, 0, $max_length);
    }
}
