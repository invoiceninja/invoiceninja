<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Models\Quote;
use App\Models\Credit;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class LocationData extends AbstractService
{
    private ?Location $businessLocation;

    private ?Location $shippingLocation;

    public function __construct(private Invoice | Quote | Credit | RecurringInvoice $entity)
    {
        $this->setLocations();
    }

    private function setLocations(): self
    {
        if (!$this->entity->location) {
            $this->businessLocation = null;
            $this->shippingLocation = null;
        } elseif ($this->entity->location->is_shipping_location) {
            $this->shippingLocation = $this->entity->location;
            $this->businessLocation = null;
        } else {
            $this->businessLocation = $this->entity->location;
            $this->shippingLocation = null;
        }

        return $this;
    }

    public function run(bool $setCountries = true): array
    {
        $location = [
            // Business Address (from business location or client default)
            'location_name' => $this->getLocationName(),
            'address' => $this->getBusinessAddress(),
            'address1' => $this->getBusinessAddress1(),
            'address2' => $this->getBusinessAddress2(),
            'city' => $this->getBusinessCity(),
            'state' => $this->getBusinessState(),
            'postal_code' => $this->getBusinessPostalCode(),
            'country' => $this->getBusinessCountry(),
            'country_name' => $this->getBusinessCountryName(),
            'country_code' => $this->getBusinessCountryCode(),

            // Shipping Address (from shipping location or client default)
            'shipping_location_name' => $this->getShippingLocationName(),
            'shipping_address' => $this->getShippingAddress(),
            'shipping_address1' => $this->getShippingAddress1(),
            'shipping_address2' => $this->getShippingAddress2(),
            'shipping_city' => $this->getShippingCity(),
            'shipping_state' => $this->getShippingState(),
            'shipping_postal_code' => $this->getShippingPostalCode(),
            'shipping_country' => $this->getShippingCountry(),
            'shipping_country_name' => $this->getShippingCountryName(),
            'shipping_country_code' => $this->getShippingCountryCode(),
            'shipping_exists' => strlen($this->getShippingAddress1()) > 0,
        ];

        if (!$setCountries) {
            unset($location['country']);
            unset($location['shipping_country']);
        }

        return $location;
    }

    private function getLocationName(): string
    {
        return $this->businessLocation ? ($this->businessLocation->name ?? '') : '';
    }

    private function getShippingLocationName(): string
    {
        return $this->shippingLocation ? ($this->shippingLocation->name ?? '') : '';
    }

    private function getBusinessCountry(): ?Country
    {

        if ($this->businessLocation) {
            return $this->businessLocation->country ?? $this->entity->company->country();
        }

        return $this->entity->client->country ?? $this->entity->company->country();

    }

    private function getShippingCountry(): ?Country
    {
        if ($this->shippingLocation) {
            return $this->shippingLocation->country ?? $this->entity->company->country();
        }

        return $this->entity->client->shipping_country ?? $this->entity->company->country();

    }

    public function getCityState()
    {
        $country = $this->getBusinessCountry();

        $swap = $country && $country->swap_postal_code;

        $city = e($this->getBusinessCity() ?: '');
        $state = e($this->getBusinessState() ?: '');
        $postalCode = e($this->getBusinessPostalCode() ?: '');

        if ($city || $state || $postalCode) {
            return $this->entity->present()->cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return false;
        }
    }

    private function getBusinessAddress(): string
    {
        $str = ' ';

        if ($address1 = $this->getBusinessAddress1()) {
            $str .= e($address1).'<br/>';
        }
        if ($address2 = $this->getBusinessAddress2()) {
            $str .= e($address2).'<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = $this->getBusinessCountryName()) {
            $str .= e($country).'<br/>';
        }

        return $str;

    }

    private function getShippingCityState(): ?string
    {
        $country = $this->getShippingCountry();

        $swap = $country && $country->swap_postal_code;

        $city = e($this->getShippingCity() ?: '');
        $state = e($this->getShippingState() ?: '');
        $postalCode = e($this->getShippingPostalCode() ?: '');

        if ($city || $state || $postalCode) {
            return $this->entity->present()->cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return null;
        }
    }

    private function getShippingAddress(): string
    {

        $str = ' ';

        if ($address1 = $this->getShippingAddress1()) {
            $str .= e($address1).'<br/>';
        }
        if ($address2 = $this->getShippingAddress2()) {
            $str .= e($address2).'<br/>';
        }
        if ($cityState = $this->getShippingCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = $this->getShippingCountryName()) {
            $str .= e($country).'<br/>';
        }

        return $str;

    }

    private function getBusinessAddress1(): string
    {
        if ($this->businessLocation) {
            return $this->businessLocation->address1 ?? '';
        }

        return $this->entity->client->address1 ?? '';
    }

    private function getBusinessAddress2(): string
    {
        if ($this->businessLocation) {
            return $this->businessLocation->address2 ?? '';
        }

        return $this->entity->client->address2 ?? '';
    }

    private function getBusinessCity(): string
    {
        if ($this->businessLocation) {
            return $this->businessLocation->city ?? '';
        }

        return $this->entity->client->city ?? '';
    }

    private function getBusinessState(): string
    {
        if ($this->businessLocation) {
            return $this->businessLocation->state ?? '';
        }

        return $this->entity->client->state ?? '';
    }

    private function getBusinessPostalCode(): string
    {
        if ($this->businessLocation) {
            return $this->businessLocation->postal_code ?? '';
        }

        return $this->entity->client->postal_code ?? '';
    }

    private function getBusinessCountryName(): string
    {
        if ($this->businessLocation) {
            return $this->businessLocation->country->name;
        }

        return $this->entity->client->country->name ?? $this->entity->company->country()->name;
    }

    private function getBusinessCountryCode(): string
    {
        if ($this->businessLocation) {
            return $this->businessLocation->country->iso_3166_2;
        }

        return $this->entity->client->country->iso_3166_2 ?? $this->entity->company->country()->iso_3166_2;
    }

    private function getShippingAddress1(): string
    {
        if ($this->shippingLocation) {
            return $this->shippingLocation->address1 ?? '';
        }

        return $this->entity->client->shipping_address1 ?? '';
    }

    private function getShippingAddress2(): string
    {
        if ($this->shippingLocation) {
            return $this->shippingLocation->address2 ?? '';
        }

        return $this->entity->client->shipping_address2 ?? '';
    }

    private function getShippingCity(): string
    {
        if ($this->shippingLocation) {
            return $this->shippingLocation->city ?? '';
        }

        return $this->entity->client->shipping_city ?? '';
    }

    private function getShippingState(): string
    {
        if ($this->shippingLocation) {
            return $this->shippingLocation->state ?? '';
        }

        return $this->entity->client->shipping_state ?? '';
    }

    private function getShippingPostalCode(): string
    {
        if ($this->shippingLocation) {
            return $this->shippingLocation->postal_code ?? '';
        }

        return $this->entity->client->shipping_postal_code ?? '';
    }

    private function getShippingCountryName(): string
    {
        if ($this->shippingLocation && $this->shippingLocation->country) {
            return $this->shippingLocation->country->name;
        }

        return $this->entity->client->shipping_country->name ?? $this->entity->company->country()->name;
    }

    private function getShippingCountryCode(): string
    {
        if ($this->shippingLocation && $this->shippingLocation->country) {
            return $this->shippingLocation->country->iso_3166_2;
        }

        return $this->entity->client->shipping_country->iso_3166_2 ?? $this->entity->company->country()->iso_3166_2;
    }
}
