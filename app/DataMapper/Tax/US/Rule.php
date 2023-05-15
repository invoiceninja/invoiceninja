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

use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\Models\Product;

/**
 * The rules apply US => US taxes using the tax calculator.
 *
 * US => Foreign taxes we check the product types still for exemptions, and we all back to the client country tax rate.
 */
class Rule extends BaseRule implements RuleInterface
{

    /** @var string $seller_region */
    public string $seller_region = 'US';
    
    /**
     * Initializes the rules and builds any required data.
     *
     * @return self
     */
    public function init(): self
    {
        $this->calculateRates();

        return $this;
    }
    
    /**
     * Override tax class, we use this when we do not modify the input taxes
     *
     * @return self
     */
    public function override(): self
    {
        return $this;
    }
    
    /**
     * Sets the correct tax rate based on the product type.
     *
     * @param  mixed $product_tax_type
     * @return self
     */
    public function taxByType($product_tax_type): self
    {

        match(intval($product_tax_type)) {
            Product::PRODUCT_TYPE_EXEMPT => $this->taxExempt(),
            Product::PRODUCT_TYPE_DIGITAL => $this->taxDigital(),
            Product::PRODUCT_TYPE_SERVICE => $this->taxService(),
            Product::PRODUCT_TYPE_SHIPPING => $this->taxShipping(),
            Product::PRODUCT_TYPE_PHYSICAL => $this->taxPhysical(),
            Product::PRODUCT_TYPE_REDUCED_TAX => $this->taxReduced(),
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $this->override(), 
            Product::PRODUCT_TYPE_ZERO_RATED => $this->zeroRated(),
            default => $this->default(),
        };
        
        return $this;
    }
    
    /**
     * Sets the tax as exempt (0)
     *
     * @return self
     */
    public function taxExempt(): self
    {
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }
    
    /**
     * Calculates the tax rate for a digital product
     *
     * @return self
     */
    public function taxDigital(): self
    {
        $this->default();

        return $this;
    }
    
    /**
     * Calculates the tax rate for a service product
     *
     * @return self
     */
    public function taxService(): self
    {
        if($this->tax_data?->txbService == 'Y') {
            $this->default();
        }

        return $this;
    }
    
    /**
     * Calculates the tax rate for a shipping product
     *
     * @return self
     */
    public function taxShipping(): self
    {
        if($this->tax_data?->txbFreight == 'Y') {
            $this->default();
        }

        return $this;
    }
    
    /**
     * Calculates the tax rate for a physical product
     *
     * @return self
     */
    public function taxPhysical(): self
    {
        $this->default();

        return $this;
    }
    
    /**
     * Calculates the tax rate for an undefined product uses the default tax rate for the client county
     *
     * @return self
     */
    public function default(): self
    {

        if($this->tax_data?->stateSalesTax == 0) {

            if($this->tax_data->originDestination == "O"){
                $tax_region = $this->client->company->tax_data->seller_subregion;
                $this->tax_rate1 = $this->invoice->client->company->tax_data->regions->US->subregions->{$tax_region}->tax_rate;
                $this->tax_name1 = "{$this->tax_data->geoState} Sales Tax";
            } else {
                $this->tax_rate1 = $this->invoice->client->company->tax_data->regions->{$this->client_region}->subregions->{$this->client_subregion}->tax_rate;
                $this->tax_name1 = $this->invoice->client->company->tax_data->regions->{$this->client_region}->subregions->{$this->client_subregion}->tax_name;

                if($this->client_region == 'US')
                    $this->tax_name1 = "{$this->client_subregion} ".$this->tax_name1;

            }

            return $this;
        }

        $this->tax_rate1 = $this->tax_data->taxSales * 100;
        $this->tax_name1 = "{$this->tax_data->geoState} Sales Tax";


        return $this;
    }
    
    public function zeroRated(): self
    {

        $this->tax_rate1 = 0;
        $this->tax_name1 = "{$this->tax_data->geoState} Zero Rated Tax";

        return $this;

    }

    /**
     * Calculates the tax rate for a reduced tax product
     *
     * @return self
     */
    public function taxReduced(): self
    {
        $this->default();

        return $this;
    }
    
    /**
     * Calculates the tax rates to be applied
     *
     * @return self
     */
    public function calculateRates(): self
    {
        return $this;
    }

    /**
     * Calculates the tax rate for a reverse tax product
     *
     * @return self
     */
    public function reverseTax(): self
    {
        $this->default();

        return $this;
    }


}
