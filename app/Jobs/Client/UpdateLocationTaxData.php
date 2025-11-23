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

namespace App\Jobs\Client;

use App\DataProviders\USStates;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Location;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class UpdateLocationTaxData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param Location $location
     * @param Company $company
     */
    public function __construct(public Location $location, protected Company $company)
    {
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        if ($this->company->account->isFreeHostedClient() || $this->location->vendor || $this->location->country_id != 840) {
            return;
        }

        $client = $this->location->client;

        try {

            if (!$this->location->state && $this->location->postal_code) {

                $this->location->update(['state' => USStates::getState($this->location->postal_code)]);
                $this->location->refresh();
            }

            $tax_provider = new \App\Services\Tax\Providers\TaxProvider($this->company, $this->location->client);

            $location_address = [
                'address2' => $this->location->address2 ?? '',
                'address1' => $this->location->address1 ?? '',
                'city' => $this->location->city ?? '',
                'state' => $this->location->state ?? '',
                'postal_code' => $this->location->postal_code ?? '',
                'country' => $this->location->country()->exists() ? $this->location->country->name : $this->company->country()->name,
            ];

            $tax_provider->setBillingAddress($location_address)
                         ->setShippingAddress($location_address)
                         ->updateLocationTaxData($this->location);


        } catch (\Exception $e) {
            nlog("Exception:: UpdateTaxData::" . $e->getMessage());
            nlog("problem getting tax data => ".$e->getMessage());
        }

    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->location->client->id.$this->company->company_key))->dontRelease()];
    }

    public function failed($exception)
    {
        nlog("UpdateLocationTaxData failed => ".$exception->getMessage());
        config(['queue.failed.driver' => null]);

    }

}
