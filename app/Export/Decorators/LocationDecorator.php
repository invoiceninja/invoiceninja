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

namespace App\Export\Decorators;

use App\Models\Location;

class LocationDecorator extends Decorator implements DecoratorInterface
{
    private $entity_key = 'location';

    public function transform(string $key, mixed $entity): mixed
    {
        $location = false;

        if ($entity instanceof Location) {
            $location = $entity;
        } elseif ($entity->location ?? false) {
            $location = $entity->location;
        }

        if ($location && method_exists($this, $key)) {
            return $this->{$key}($location);
        } elseif ($location && ($location->{$key} ?? false)) {
            return $location->{$key};
        }

        return '';
    }

    public function name(Location $location)
    {
        return $location->name ?: '';
    }

    public function country_id(Location $location)
    {
        return $location->country ? ctrans("texts.country_{$location->country->name}") : '';
    }

    public function is_shipping_location(Location $location)
    {
        return $location->is_shipping_location ? ctrans('texts.yes') : ctrans('texts.no');
    }
}
