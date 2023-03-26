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

namespace App\DataMapper\Tax\us;

use App\Models\Client;
use App\Models\Product;
use App\DataMapper\Tax\RuleInterface;
use App\DataMapper\Tax\ZipTax\Response;

class Rule implements RuleInterface
{

    public float $al_sales_tax_rate = 4; // Alabama
    public float $ak_sales_tax_rate = 0; // Alaska
    public float $az_sales_tax_rate = 5.6; // Arizona
    public float $ar_sales_tax_rate = 6.5; // Arkansas
    public float $ca_sales_tax_rate = 7.25; // California - https://services.maps.cdtfa.ca.gov/api/taxrate/GetRateByAddress?address=2444+s+alameda+st&city=los+angeles&zip=90058
    public float $co_sales_tax_rate = 2.9; // Colorado
    public float $ct_sales_tax_rate = 6.35; // Connecticut
    public float $de_sales_tax_rate = 0; // Delaware
    public float $fl_sales_tax_rate = 6; // Florida
    public float $ga_sales_tax_rate = 4; // Georgia
    public float $hi_sales_tax_rate = 4; // Hawaii
    public float $id_sales_tax_rate = 6; // Idaho
    public float $il_sales_tax_rate = 6.25; // Illinois
    public float $in_sales_tax_rate = 7; // Indiana
    public float $ia_sales_tax_rate = 6; // Iowa
    public float $ks_sales_tax_rate = 6.5; // Kansas
    public float $ky_sales_tax_rate = 6; // Kentucky
    public float $la_sales_tax_rate = 4.45; // Louisiana
    public float $me_sales_tax_rate = 5.5; // Maine
    public float $md_sales_tax_rate = 6; // Maryland
    public float $ma_sales_tax_rate = 6.25; // Massachusetts
    public float $mi_sales_tax_rate = 6; // Michigan
    public float $mn_sales_tax_rate = 6.875; // Minnesota
    public float $ms_sales_tax_rate = 7; // Mississippi
    public float $mo_sales_tax_rate = 4.225; // Missouri
    public float $mt_sales_tax_rate = 0; // Montana
    public float $ne_sales_tax_rate = 5.5; // Nebraska
    public float $nv_sales_tax_rate = 6.85; // Nevada
    public float $nh_sales_tax_rate = 0; // New Hampshire
    public float $nj_sales_tax_rate = 6.625; // New Jersey
    public float $nm_sales_tax_rate = 5.125; // New Mexico
    public float $ny_sales_tax_rate = 4; // New York
    public float $nc_sales_tax_rate = 4.75; // North Carolina
    public float $nd_sales_tax_rate = 5; // North Dakota
    public float $oh_sales_tax_rate = 5.75; // Ohio
    public float $ok_sales_tax_rate = 4.5; // Oklahoma
    public float $or_sales_tax_rate = 0; // Oregon
    public float $pa_sales_tax_rate = 6; // Pennsylvania
    public float $ri_sales_tax_rate = 7; // Rhode Island
    public float $sc_sales_tax_rate = 6; // South Carolina
    public float $sd_sales_tax_rate = 4.5; // South Dakota
    public float $tn_sales_tax_rate = 7; // Tennessee
    public float $tx_sales_tax_rate = 6.25; // Texas
    public float $ut_sales_tax_rate = 4.7; // Utah
    public float $vt_sales_tax_rate = 6; // Vermont
    public float $va_sales_tax_rate = 5.3; // Virginia
    public float $wa_sales_tax_rate = 6.5; // Washington
    public float $wv_sales_tax_rate = 6; // West Virginia
    public float $wi_sales_tax_rate = 5; // Wisconsin
    public float $wy_sales_tax_rate = 4; // Wyoming
    public float $dc_sales_tax_rate = 6; // District of Columbia
    public float $pr_sales_tax_rate = 11.5; // Puerto Rico

    public string $tax_name1 = '';
    public float $tax_rate1 = 0;

    public string $tax_name2 = '';
    public float $tax_rate2 = 0;
    
    public string $tax_name3 = '';
    public float $tax_rate3 = 0;
    
    public ?Client $client;

    public ?Response $tax_data;

    public function __construct()
    {
    }

    public function setTaxData(Response $tax_data): self
    {
        $this->tax_data = $tax_data;

        return $this;
    }

    public function setClient(Client $client):self 
    {
        $this->client = $client;

        return $this;
    }

    public function tax(): self
    {
        if($this->client->is_tax_exempt)
            return $this->taxExempt();

        $this->tax_rate1 = $this->tax_data->taxSales * 100;
        $this->tax_name1 = "{$this->tax_data->geoState} Sales Tax";

        return $this;

    }

    public function taxByType(?int $product_tax_type): self
    {
        if(!$product_tax_type)
            return $this;


        if ($this->client->is_tax_exempt) {
            return $this->taxExempt();
        }

        match($product_tax_type){
            Product::PRODUCT_TAX_EXEMPT => $this->taxExempt(),
            Product::PRODUCT_TYPE_DIGITAL => $this->taxDigital(),
            Product::PRODUCT_TYPE_SERVICE => $this->taxService(),
            Product::PRODUCT_TYPE_SHIPPING => $this->taxShipping(),
            Product::PRODUCT_TYPE_PHYSICAL => $this->taxPhysical(),
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
        
        $this->tax_name1 = 'Tax Exempt';
        $this->tax_rate1 = 0;

        return $this;
    }

    public function taxReduced(): self
    {
        $this->tax();

        return $this;
    }
}
