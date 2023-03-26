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
use App\DataMapper\Tax\RuleInterface;
use App\DataMapper\Tax\ZipTax\Response;

class Rule implements RuleInterface
{
    public float $vat_rate = 19;

    public float $vat_threshold = 10000;

    public float $vat_reduced_rate = 7;

    public float $vat_reduced_threshold = 10000;

    public float $at_vat_rate = 20; // Austria
    
    public float $be_vat_rate = 21; // Belgium
    
    public float $bg_vat_rate = 20; // Bulgaria
    
    public float $hr_vat_rate = 25; // Croatia
    
    public float $cy_vat_rate = 19; // Cyprus
    
    public float $cz_vat_rate = 21; // Czech Republic
    
    public float $dk_vat_rate = 25; // Denmark
    
    public float $ee_vat_rate = 20; // Estonia
    
    public float $fi_vat_rate = 24; // Finland
    
    public float $fr_vat_rate = 20; // France
    
    public float $de_vat_rate = 19; // Germany
    
    public float $gr_vat_rate = 24; // Greece
    
    public float $hu_vat_rate = 27; // Hungary
    
    public float $ie_vat_rate = 23; // Ireland
    
    public float $it_vat_rate = 22; // Italy
    
    public float $lv_vat_rate = 21; // Latvia
    
    public float $lt_vat_rate = 21; // Lithuania
    
    public float $lu_vat_rate = 17; // Luxembourg
    
    public float $mt_vat_rate = 18; // Malta
    
    public float $nl_vat_rate = 21; // Netherlands
    
    public float $pl_vat_rate = 23; // Poland
    
    public float $pt_vat_rate = 23; // Portugal
    
    public float $ro_vat_rate = 19; // Romania
    
    public float $sk_vat_rate = 20; // Slovakia
    
    public float $si_vat_rate = 22; // Slovenia
    
    public float $es_vat_rate = 21; // Spain
    
    public float $se_vat_rate = 25; // Sweden
    
    public float $gb_vat_rate = 20; // United Kingdom

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

    public function tax(): self
    {
        if($this->client->is_tax_exempt)
            return $this->taxExempt();
        

        $this->tax_name1 = $this->vat_rate;
        $this->tax_rate1 = "VAT";

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
        $this->tax_name1 = 'VAT';

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
        if($this->tax_data->txbService == 'Y')
            $this->tax();

        return $this;
    }

    public function taxShipping(): self
    {
        if($this->tax_data->txbFreight == 'Y')
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
