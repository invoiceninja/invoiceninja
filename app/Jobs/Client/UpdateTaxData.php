<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Client;

use App\DataMapper\Tax\ZipTax\Response;
use App\Models\Client;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\DataProviders\USStates;
use App\Utils\Traits\MakesHash;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class UpdateTaxData implements ShouldQueue
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
     * @param Client $client
     * @param Company $company
     */
    public function __construct(public Client $client, protected Company $company)
    {
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        if($this->company->account->isFreeHostedClient())
            return;
            
        $tax_provider = new \App\Services\Tax\Providers\TaxProvider($this->company, $this->client);
        
        try {
            
            $tax_provider->updateClientTaxData();
        
            if (!$this->client->state && $this->client->postal_code) {

                $this->client->state = USStates::getState($this->client->postal_code);
                $this->client->saveQuietly();

            }

        
        }catch(\Exception $e){
            nlog("problem getting tax data => ".$e->getMessage());
        }

        /*
        if(!$tax_provider->updatedTaxStatus() && $this->client->country_id == 840){

            $calculated_state = false;

            if(array_key_exists($this->client->shipping_state, USStates::get())) {
                $calculated_state = $this->client->shipping_state;
                $calculated_postal_code = $this->client->shipping_postal_code;
                $calculated_city = $this->client->shipping_city;
            }
            elseif(array_key_exists($this->client->state, USStates::get())){
                $calculated_state = $this->client->state;
                $calculated_postal_code = $this->client->postal_code;
                $calculated_city = $this->client->city;
            }
            else {

                try{
                    $calculated_state = USStates::getState($this->client->shipping_postal_code);
                    $calculated_postal_code = $this->client->shipping_postal_code;
                    $calculated_city = $this->client->shipping_city;
                }
                catch(\Exception $e){
                    nlog("could not calculate state from postal code => {$this->client->shipping_postal_code} or from state {$this->client->shipping_state}");
                }

                if(!$calculated_state) {
                    try {
                        $calculated_state = USStates::getState($this->client->postal_code);
                        $calculated_postal_code = $this->client->postal_code;
                        $calculated_city = $this->client->city;
                    } catch(\Exception $e) {
                        nlog("could not calculate state from postal code => {$this->client->postal_code} or from state {$this->client->state}");
                    }
                }

                if($this->company->tax_data?->seller_subregion)
                    $calculated_state =  $this->company->tax_data?->seller_subregion;

                    nlog("i am trying");

                if(!$calculated_state) {
                    nlog("could not determine state");
                    return;
                }

            }
                        
            $data = [
                'seller_subregion' => $this->company->tax_data?->seller_subregion ?: '',
                'geoPostalCode' => $this->client->postal_code ?? '',
                'geoCity' => $this->client->city ?? '',
                'geoState' => $calculated_state,
                'taxSales' => $this->company->tax_data->regions->US->subregions?->{$calculated_state}?->taxSales ?? 0,
            ];

            $tax_data = new Response($data);

            $this->client->tax_data = $tax_data;
            $this->client->saveQuietly();

        }
      */
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->client->id.$this->company->id)];
    }

    public function failed($exception)
    {
        nlog("UpdateTaxData failed => ".$exception->getMessage());
        config(['queue.failed.driver' => null]);

    }

}