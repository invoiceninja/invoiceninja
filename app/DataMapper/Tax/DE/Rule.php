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

use App\Models\Product;
use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;

class Rule extends BaseRule implements RuleInterface
{
    public string $vendor_iso_3166_2 = 'DE';

    public bool $consumer_tax_exempt = false;

    public bool $business_tax_exempt = false;

    public bool $eu_business_tax_exempt = true;

    public bool $foreign_business_tax_exempt = true;

    public bool $foreign_consumer_tax_exempt = true;

    public float $tax_rate = 0;

    public float $reduced_tax_rate = 0;

    public function init(): self
    {
        $this->calculateRates();
        
        return $this;
    }

    public function tax($item = null): self
    {
        
        if ($this->client->is_tax_exempt) {

            return $this->taxExempt();

        } elseif ($this->client->company->tax_data->regions->EU->tax_all_subregions || $this->client->company->tax_data->regions->EU->subregions->{$this->client_subregion}->apply_tax) {

            $this->taxByType($item->tax_id);

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
        $this->tax_rate1 = $this->reduced_tax_rate;
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

        $this->tax_rate1 = $this->tax_rate;
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
            // nlog("tax exempt");
            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;
        }
        elseif($this->client_subregion != $this->vendor_iso_3166_2 && in_array($this->client_subregion, $this->eu_country_codes) && $this->client->has_valid_vat_number && $this->eu_business_tax_exempt)
        {
            // nlog("euro zone and tax exempt");
            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;
        }
        elseif(!in_array($this->client_subregion, $this->eu_country_codes) && ($this->foreign_consumer_tax_exempt || $this->foreign_business_tax_exempt)) //foreign + tax exempt
        {
            // nlog("foreign and tax exempt");
            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;
        }
        elseif(in_array($this->client_subregion, $this->eu_country_codes) && !$this->client->has_valid_vat_number) //eu country / no valid vat 
        {   
            if(($this->vendor_iso_3166_2 != $this->client_subregion) && $this->client->company->tax_data->regions->EU->has_sales_above_threshold)
            {
                // nlog("eu zone with sales above threshold");
                $this->tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->tax_rate;
                $this->reduced_tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->reduced_tax_rate;
            }
            else {
                // nlog("EU with intra-community supply ie DE to DE");
                $this->tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->tax_rate;
                $this->reduced_tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->reduced_tax_rate;
            }
        }
        else {
            // nlog("default tax");
            $this->tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->tax_rate;
            $this->reduced_tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->reduced_tax_rate;
        }

        return $this;

    }


}
