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

namespace App\DataMapper\Tax\de;

use App\Models\Client;
use App\Models\Product;
use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\DataMapper\Tax\ZipTax\Response;

class Rule extends BaseRule implements RuleInterface
{

    public float $vat_rate = 19;

    public float $vat_threshold = 10000;

    public float $vat_reduced_rate = 7;

    public float $vat_reduced_threshold = 10000;

    public bool $consumer_tax_exempt = false;

    public bool $business_tax_exempt = true;

    public bool $eu_business_tax_exempt = true;

    public bool $foreign_business_tax_exempt = true;

    public bool $foreign_consumer_tax_exempt = true;

    public string $tax_name1 = '';

    public float $tax_rate1 = 0;

    public string $tax_name2 = '';
    
    public float $tax_rate2 = 0;
    
    public string $tax_name3 = '';
    
    public float $tax_rate3 = 0;
    
    protected ?Client $client;

    protected ?Response $tax_data;

    public function __construct()
    {
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
    public function tax(): self
    {
        if($this->client->is_tax_exempt || $this->client->has_valid_vat_number)
            return $this->taxExempt();
        
        $this->tax_name1 = $this->vat_rate;
        $this->tax_rate1 = "MwSt.";

        return $this;

    }

    public function taxByType(?int $product_tax_type): self
    {

        if ($this->client->is_tax_exempt) {
            return $this->taxExempt();
        }

        if(!$product_tax_type)
            return $this;

        match($product_tax_type){
            Product::PRODUCT_TAX_EXEMPT => $this->taxExempt(),
            Product::PRODUCT_TYPE_DIGITAL => $this->taxDigital(),
            Product::PRODUCT_TYPE_SERVICE => $this->taxService(),
            Product::PRODUCT_TYPE_SHIPPING => $this->taxShipping(),
            Product::PRODUCT_TYPE_PHYSICAL => $this->taxPhysical(),
            Product::PRODUCT_TYPE_REDUCED_TAX => $this->taxReduced(),
            default => $this->default(),
        };
        
        return $this;
    }

    public function taxReduced(): self
    {
        $this->tax_rate1 = $this->vat_reduced_rate;
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
        $this->tax();

        return $this;
    }

    public function taxService(): self
    {
        $this->tax();

        return $this;
    }

    public function taxShipping(): self
    {
        $this->tax();

        return $this;
    }

    public function taxPhysical(): self
    {
        $this->tax();

        return $this;
    }

    public function default(): self
    {
        
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }
}
