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

namespace App\Services\EDocument\Support;

/**
 * Peppol routing_id GLN (ICD 0088): exactly {@see self::ID_LENGTH} digits after the "0088:" prefix.
 * No GS1 check-digit or other semantic validation — format only.
 */
final class GlnIdentifier
{
    public const ID_LENGTH = 13;

    /**
     * @return string|null The 13-digit id part after "0088:", or null
     */
    public static function tryParse(string $value): ?string
    {
        $value = trim($value);

        if (!str_starts_with($value, '0088:')) {
            return null;
        }

        $id = substr($value, 5);

        if (strlen($id) !== self::ID_LENGTH || !ctype_digit($id)) {
            return null;
        }

        return $id;
    }

    public static function isValid(string $value): bool
    {
        return self::tryParse($value) !== null;
    }
}
