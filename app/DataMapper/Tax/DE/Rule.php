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

namespace App\DataMapper\Tax\DE;

use App\Models\Client;
use App\Models\Product;
use Illuminate\Support\Str;
use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\DataMapper\Tax\ZipTax\Response;

class Rule extends BaseRule implements RuleInterface
{
    public string $vendor_country_code = 'DE';

    public string $client_country_code = 'DE';

    public bool $consumer_tax_exempt = false;

    public bool $business_tax_exempt = false;

    public bool $eu_business_tax_exempt = true;

    public bool $foreign_business_tax_exempt = true;

    public bool $foreign_consumer_tax_exempt = true;

    public string $tax_name1 = '';

    public float $tax_rate1 = 0;

    public string $tax_name2 = '';
    
    public float $tax_rate2 = 0;
    
    public string $tax_name3 = '';
    
    public float $tax_rate3 = 0;

    public float $vat_rate = 0;

    public float $reduced_vat_rate = 0;

    protected ?Client $client;

    protected ?Response $tax_data;

    public function __construct()
    {
    }

    public function init(): self
    {
        $this->client_country_code = $this->client->shipping_country ? $this->client->shipping_country->iso_3166_2 : $this->client->country->iso_3166_2;
        $this->calculateRates();
        
        return $this;
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function setTaxData(Response $tax_data): self
    {
        $this->tax_data = $tax_data;

        return $this;
    }

    //need to add logic here to capture if
    public function tax($type): self
    {
        

        if ($this->client->is_tax_exempt) {

            return $this->taxExempt();

        } elseif ($this->client->company->tax_data->regions->EU->tax_all_subregions || $this->client->company->tax_data->regions->EU->subregions->{$this->client_country_code}->apply_tax) {

            $this->taxByType($type);

            return $this;
        }

        

        return $this;

    }

    public function taxByType($product_tax_type): self
    {

        if ($this->client->is_tax_exempt) {
            return $this->taxExempt();
        }

        match($product_tax_type){
            Product::PRODUCT_TYPE_EXEMPT => $this->taxExempt(),
            Product::PRODUCT_TYPE_DIGITAL => $this->taxDigital(),
            Product::PRODUCT_TYPE_SERVICE => $this->taxService(),
            Product::PRODUCT_TYPE_SHIPPING => $this->taxShipping(),
            Product::PRODUCT_TYPE_PHYSICAL => $this->taxPhysical(),
            Product::PRODUCT_TYPE_REDUCED_TAX => $this->taxReduced(),
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $this->override(),
            default => $this->default(),
        };
        
        return $this;
    }

    public function taxReduced(): self
    {
        $this->tax_rate1 = $this->reduced_vat_rate;
        $this->tax_name1 = 'ermäßigte MwSt.';

        return $this;
    }

    public function taxExempt(): self
    {
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

    public function taxDigital(): self
    {
        // $this->tax();

        return $this;
    }

    public function taxService(): self
    {
        // $this->tax();

        return $this;
    }

    public function taxShipping(): self
    {
        // $this->tax();

        return $this;
    }

    public function taxPhysical(): self
    {
        // $this->tax();

        $this->tax_rate1 = $this->vat_rate;
        $this->tax_name1 = 'MwSt.';
        return $this;
    }

    public function default(): self
    {
        
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

    public function override(): self
    {
        return $this;
    }

    public function calculateRates(): self
    {

        if ($this->client->is_tax_exempt) {
            $this->vat_rate = 0;
            $this->reduced_vat_rate = 0;
        }
        elseif($this->client_country_code != $this->vendor_country_code && in_array($this->client_country_code, $this->eu_country_codes) && $this->client->has_valid_vat_number && $this->eu_business_tax_exempt)
        {
            $this->vat_rate = 0;
            $this->reduced_vat_rate = 0;
            // nlog("euro zone and tax exempt");
        }
        elseif(!in_array(strtoupper($this->client_country_code), $this->eu_country_codes) && ($this->foreign_consumer_tax_exempt || $this->foreign_business_tax_exempt)) //foreign + tax exempt
        {
            $this->vat_rate = 0;
            $this->reduced_vat_rate = 0;
            // nlog("foreign and tax exempt");
        }
        elseif(in_array(strtoupper($this->client_country_code), $this->eu_country_codes) && !$this->client->has_valid_vat_number) //eu country / no valid vat 
        {   
            if(($this->vendor_country_code != $this->client_country_code) && $this->client->company->tax_data->regions->EU->has_sales_above_threshold)
            {
                $this->vat_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->vat_rate;
                $this->reduced_vat_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->reduced_vat_rate;
                // nlog("eu zone with sales above threshold");
            }
            else {
                $this->vat_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->vat_rate;
                $this->reduced_vat_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->reduced_vat_rate;
                // nlog("same eu country with");
            }
        }
        else {
            $this->vat_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->vat_rate;
            $this->reduced_vat_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->reduced_vat_rate;
            // nlog("default tax");
        }

        return $this;

    }


}
