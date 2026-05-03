<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\Tax\SG;

use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\Models\Product;

class Rule extends BaseRule implements RuleInterface
{
    /** @var string $seller_region */
    public string $seller_region = 'SG';

    /** @var bool $consumer_tax_exempt */
    public bool $consumer_tax_exempt = false;

    /** @var bool $business_tax_exempt */
    public bool $business_tax_exempt = false;

    /** @var bool $eu_business_tax_exempt */
    public bool $eu_business_tax_exempt = false;

    /** @var bool $foreign_business_tax_exempt */
    public bool $foreign_business_tax_exempt = false;

    /** @var bool $foreign_consumer_tax_exempt */
    public bool $foreign_consumer_tax_exempt = false;

    /** @var float $tax_rate */
    public float $tax_rate = 0;

    /** @var float $reduced_tax_rate */
    public float $reduced_tax_rate = 0;

    public string $tax_name1 = 'GST';

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

        match (intval($item->tax_id)) {
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

    public function reverseTax($item): self
    {
        $this->tax_rate1 = 0;
        $this->tax_name1 = 'GST';

        return $this;
    }

    public function taxReduced($item): self
    {
        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    public function zeroRated($item): self
    {
        $this->tax_rate1 = 0;
        $this->tax_name1 = 'GST';

        return $this;
    }

    public function taxExempt($item): self
    {
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

    public function taxDigital($item): self
    {
        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    public function taxService($item): self
    {
        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    public function taxShipping($item): self
    {
        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    public function taxPhysical($item): self
    {
        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    public function default($item): self
    {
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

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
     * Singapore has a single GST rate applied nationally.
     * - Domestic sales: 9% standard
     * - Foreign business/consumer: 0% (export)
     */
    public function calculateRates(): self
    {
        if ($this->client->is_tax_exempt) {
            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;

            return $this;
        }

        if ($this->client_subregion === 'SG') {
            $this->tax_rate = $this->client->company->tax_data->regions->SG->subregions->SG->tax_rate ?? 0;
            $this->reduced_tax_rate = $this->client->company->tax_data->regions->SG->subregions->SG->reduced_tax_rate ?? 0;

            return $this;
        }

        $this->tax_rate = 0;
        $this->reduced_tax_rate = 0;

        return $this;
    }
}
