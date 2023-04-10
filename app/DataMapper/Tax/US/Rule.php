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

namespace App\DataMapper\Tax\US;

use App\Models\Client;
use App\Models\Product;
use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\DataMapper\Tax\ZipTax\Response;

class Rule extends BaseRule implements RuleInterface
{

    public function override(): self 
    { 
        return $this;
    }

    public function setTaxData(Response $tax_data): self
    {
        $this->tax_data = $tax_data;

        return $this;
    }

    public function setClient(Client $client):self 
    {
        $this->client = $client;
        $this->client_iso_3166_2 = $client->country->iso_3166_2;

        return $this;
    }

    public function tax($type): self
    {
        
        if ($this->client->is_tax_exempt) {
            return $this->taxExempt();
        }
        else if($this->client->company->tax_data->regions->US->tax_all_subregions || $this->client->company->tax_data->regions->US->subregions->{$this->tax_data->geoState}->apply_tax){

            $this->taxByType($type);

            return $this;
        }
        else if($this->client->company->tax_data->regions->{$this->client_region}->tax_all_subregions || $this->client->company->tax_data->regions->{$this->client_region}->subregions->{$this->client_iso_3166_2}->apply_tax){ //other regions outside of US

        }
        return $this;

    }

    public function taxByType($product_tax_type): self
    {

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

    public function taxExempt(): self
    {
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

    public function taxDigital(): self
    {
        $this->default();

        return $this;
    }

    public function taxService(): self
    {
        if($this->tax_data->txbService == 'Y')
            $this->default();

        return $this;
    }

    public function taxShipping(): self
    {
        if($this->tax_data->txbFreight == 'Y')
            $this->default();

        return $this;
    }

    public function taxPhysical(): self
    {
        $this->default();

        return $this;
    }

    public function default(): self
    {
                
        $this->tax_rate1 = $this->tax_data->taxSales * 100;
        $this->tax_name1 = "{$this->tax_data->geoState} Sales Tax";

        return $this;
    }

    public function taxReduced(): self
    {
        $this->default();

        return $this;
    }

    public function init(): self
    {
        return $this;
    }

    public function calculateRates(): self
    {
        return $this;
    }
}
