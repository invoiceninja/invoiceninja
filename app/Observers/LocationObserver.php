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

namespace App\Observers;

use App\Models\Location;
use App\Jobs\Client\UpdateLocationTaxData;

class LocationObserver
{
    /**
     * Handle the location "created" event.
     *
     * @param Location $location
     * @return void
     */
    public function created(Location $location)
    {
        if ($location->country_id == 840 && $location->company->calculate_taxes && !$location->company->account->isFreeHostedClient()) {
            UpdateLocationTaxData::dispatch($location, $location->company);
        }

    }

    /**
     * Handle the location "updated" event.
     *
     * @param Location $location
     * @return void
     */
    public function updated(Location $location)
    {

        if ($location->getOriginal('postal_code') != $location->postal_code && $location->country_id == 840 && $location->company->calculate_taxes && !$location->company->account->isFreeHostedClient()) {
            UpdateLocationTaxData::dispatch($location, $location->company);
        }

    }

    /**
     * Handle the location "deleted" event.
     *
     * @param Location $location
     * @return void
     */
    public function deleted(Location $location)
    {

    }

    /**
     * Handle the location "restored" event.
     *
     * @param Location $location
     * @return void
     */
    public function restored(Location $location)
    {

    }

    /**
     * Handle the location "force deleted" event.
     *
     * @param Location $location
     * @return void
     */
    public function forceDeleted(Location $location)
    {
        //
    }
}
