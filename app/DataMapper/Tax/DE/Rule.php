<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Tax\DE;

use App\DataMapper\InvoiceItem;
use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\Models\Product;

class Rule extends BaseRule implements RuleInterface
{
    /** @var string $seller_region */
    public string $seller_region = 'EU';

    /** @var bool $consumer_tax_exempt */
    public bool $consumer_tax_exempt = false;

    /** @var bool $business_tax_exempt */
    public bool $business_tax_exempt = false;

    /** @var bool $eu_business_tax_exempt */
    public bool $eu_business_tax_exempt = true;

    /** @var bool $foreign_business_tax_exempt */
    public bool $foreign_business_tax_exempt = false;

    /** @var bool $foreign_consumer_tax_exempt */
    public bool $foreign_consumer_tax_exempt = false;

    /** @var float $tax_rate */
    public float $tax_rate = 0;

    /** @var float $reduced_tax_rate */
    public float $reduced_tax_rate = 0;

    public string $tax_name1 = 'MwSt.';

    private string $tax_name;
    /**
     * Initializes the rules and builds any required data.
     *
     * @return self
     */
    public function init(): self
    {
        $this->tax_name = $this->tax_name1;
        $this->calculateRates();

        return $this;
    }

    /**
     * Sets the correct tax rate based on the product type.
     *
     * @param  mixed $item
     * @return self
     */
    public function taxByType($item): self
    {

        if ($this->client->is_tax_exempt || !property_exists($item, 'tax_id') || (isset($item->type_id) && $item->type_id == '5')) {
            return $this->taxExempt($item);
        }

        match(intval($item->tax_id)) {
            Product::PRODUCT_TYPE_EXEMPT => $this->taxExempt($item),
            Product::PRODUCT_TYPE_DIGITAL => $this->taxDigital($item),
            Product::PRODUCT_TYPE_SERVICE => $this->taxService($item),
            Product::PRODUCT_TYPE_SHIPPING => $this->taxShipping($item),
            Product::PRODUCT_TYPE_PHYSICAL => $this->taxPhysical($item),
            Product::PRODUCT_TYPE_REDUCED_TAX => $this->taxReduced($item),
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $this->override($item),
            Product::PRODUCT_TYPE_ZERO_RATED => $this->zeroRated($item),
            Product::PRODUCT_TYPE_REVERSE_TAX => $this->reverseTax($item),
            default => $this->default($item),
        };

        return $this;
    }

    /**
     * Calculates the tax rate for a reduced tax product
     *
     * @return self
     */
    public function reverseTax($item): self
    {
        $this->tax_name1 = $this->tax_name;
        $this->tax_rate1 = 0;

        return $this;
    }

    /**
     * Calculates the tax rate for a reduced tax product
     *
     * @return self
     */
    public function taxReduced($item): self
    {

        $this->tax_name1 = $this->tax_name;
        $this->tax_rate1 = $this->reduced_tax_rate;

        return $this;
    }

    /**
     * Calculates the tax rate for a zero rated tax product
     *
     * @return self
     */
    public function zeroRated($item): self
    {

        $this->tax_name1 = $this->tax_name;
        $this->tax_rate1 = 0;

        return $this;
    }


    /**
     * Calculates the tax rate for a tax exempt product
     *
     * @return self
     */
    public function taxExempt($item): self
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
    public function taxDigital($item): self
    {

        $this->tax_name1 = $this->tax_name;
        $this->tax_rate1 = $this->tax_rate;

        return $this;
    }

    /**
     * Calculates the tax rate for a service product
     *
     * @return self
     */
    public function taxService($item): self
    {

        $this->tax_name1 = $this->tax_name;
        $this->tax_rate1 = $this->tax_rate;

        return $this;
    }

    /**
     * Calculates the tax rate for a shipping product
     *
     * @return self
     */
    public function taxShipping($item): self
    {

        $this->tax_name1 = $this->tax_name;
        $this->tax_rate1 = $this->tax_rate;

        return $this;
    }

    /**
     * Calculates the tax rate for a physical product
     *
     * @return self
     */
    public function taxPhysical($item): self
    {

        $this->tax_name1 = $this->tax_name;
        $this->tax_rate1 = $this->tax_rate;

        return $this;
    }

    /**
     * Calculates the tax rate for a default product
     *
     * @return self
     */
    public function default($item): self
    {

        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

    /**
     * Calculates the tax rate for an override product
     *
     * @return self
     */
    public function override($item): self
    {

        $this->tax_rate1 = $item->tax_rate1;
        $this->tax_name1 = $item->tax_name1;
        $this->tax_rate2 = $item->tax_rate2;
        $this->tax_name2 = $item->tax_name2;
        $this->tax_rate3 = $item->tax_rate3;
        $this->tax_name3 = $item->tax_name3;

        return $this;
    }

    /**
     * Calculates the tax rates based on the client's location.
     *
     * @return self
     */
    public function calculateRates(): self
    {
        if ($this->client->is_tax_exempt) {
            // nlog("tax exempt");
            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;
        } elseif($this->client_subregion != $this->client->company->tax_data->seller_subregion && in_array($this->client_subregion, $this->eu_country_codes) && $this->client->vat_number && $this->client->has_valid_vat_number && $this->eu_business_tax_exempt) {
            // nlog("euro zone and tax exempt");
            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;
        } elseif(!in_array($this->client_subregion, $this->eu_country_codes) && ($this->foreign_consumer_tax_exempt || $this->foreign_business_tax_exempt)) { //foreign + tax exempt
            // nlog("foreign and tax exempt");
            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;
        } elseif(!in_array($this->client_subregion, $this->eu_country_codes)) {
            $this->defaultForeign();
        } elseif(in_array($this->client_subregion, $this->eu_country_codes) && ((strlen($this->client->vat_number ?? '') == 1) || !$this->client->has_valid_vat_number)) { //eu country / no valid vat
            if($this->client->company->tax_data->seller_subregion != $this->client_subregion) {
                // nlog("eu zone with sales above threshold");
                $this->tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->tax_rate ?? 0;
                $this->reduced_tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->reduced_tax_rate ?? 0;
            } else {
                // nlog("EU with intra-community supply ie DE to DE");
                $this->tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->tax_rate ?? 0;
                $this->reduced_tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->reduced_tax_rate ?? 0;
            }
        } else {
            // nlog("default tax");
            $this->tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->tax_rate ?? 0;
            $this->reduced_tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->company->country()->iso_3166_2}->reduced_tax_rate ?? 0;
        }

        return $this;

    }

}
