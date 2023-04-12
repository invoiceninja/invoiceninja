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

class Rule extends BaseRule implements RuleInterface
{
    public string $seller_region = 'US';

    /**
     * The rules apply US => US taxes using the tax calculator.
     * 
     * US => Foreign taxes we check the product types still for exemptions, and we all back to the client country tax rate.
     */
    public function init(): self
    {
        $this->calculateRates();

        return $this;
    }

    public function override(): self
    {
        return $this;
    }

    public function taxByType($product_tax_type): self
    {

        match($product_tax_type) {
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
        if($this->tax_data->txbService == 'Y') {
            $this->default();
        }

        return $this;
    }

    public function taxShipping(): self
    {
        if($this->tax_data->txbFreight == 'Y') {
            $this->default();
        }

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

    public function calculateRates(): self
    {
        if($this->client_region != 'US' && $this->isTaxableRegion())
            return $this;

        return $this;
    }
}
