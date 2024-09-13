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

namespace App\Services\EDocument\Standards\Settings;

class PropertyResolver
{
    public static function resolve($object, string $propertyPath)
    {
        $pathSegments = explode('.', $propertyPath);

        return self::traverse($object, $pathSegments);
    }

    private static function traverse($object, array $pathSegments)
    {
        if (empty($pathSegments)) {
            return null;
        }

        $currentProperty = array_shift($pathSegments);

        if (is_object($object) && isset($object->{$currentProperty})) {
            $nextObject = $object->{$currentProperty};
        } elseif (is_array($object) && array_key_exists($currentProperty, $object)) {
            $nextObject = $object[$currentProperty];
        } else {
            return null;
        }

        if (empty($pathSegments)) {
            return $nextObject;
        }

        return self::traverse($nextObject, $pathSegments);
    }
}
