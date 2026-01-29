<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

use App\Models\Product;

/**
 * Holds the QuickBooks income account ID per product type.
 *
 * Child properties map Product::PRODUCT_TYPE_* to QuickBooks account ID (string|null).
 * Initialized with no values; call setAccountId() or assign properties to populate.
 */
class IncomeAccountMap
{
    public ?string $physical = null;

    public ?string $service = null;

    public ?string $digital = null;

    public ?string $shipping = null;

    public ?string $exempt = null;

    public ?string $reduced_tax = null;

    public ?string $override_tax = null;

    public ?string $zero_rated = null;

    public ?string $reverse_tax = null;

    public ?string $intra_community = null;

    /**
     * Initialize from attributes array.
     * Accepts array with int keys (Product::PRODUCT_TYPE_*) or string keys (property names).
     */
    public function __construct(array $attributes = [])
    {
        $this->physical = $attributes['physical'] ?? null;
        $this->service = $attributes['service'] ?? null;
        $this->digital = $attributes['digital'] ?? null;
        $this->shipping = $attributes['shipping'] ?? null;
        $this->exempt = $attributes['exempt'] ?? null;
        $this->reduced_tax = $attributes['reduced_tax'] ?? null;
        $this->override_tax = $attributes['override_tax'] ?? null;
        $this->zero_rated = $attributes['zero_rated'] ?? null;
        $this->reverse_tax = $attributes['reverse_tax'] ?? null;
        $this->intra_community = $attributes['intra_community'] ?? null;
    }

    
    /**
     * getAccountId
     *
     * Gets the Quickbooks Income Account ID for a given product tax_id.
     * @param  string $product_tax_id
     * @return string|null
     */
    public function getAccountId(?string $product_tax_id): ?string
    {        
        /** 
         * @var string|null $prop 
         * 
         * Translates "2" => "service"
         * 
         * */
        $prop = $this->getPropertyName($product_tax_id);

        return $prop ? $this->{$prop} : null;
    }
    
    /**
     * getPropertyName
     * 
     * Tranlates the $item->tax_id => property name.
     *
     * Gets the property name for a given product tax_id.
     * @param  int|string $key
     * @return string|null
     */
    private function getPropertyName(int|string $key): ?string
    {
        return match ((string)$key) {
            (string)Product::PRODUCT_TYPE_PHYSICAL => 'physical',
            (string)Product::PRODUCT_TYPE_SERVICE => 'service',
            (string)Product::PRODUCT_TYPE_DIGITAL => 'digital',
            (string)Product::PRODUCT_TYPE_SHIPPING => 'shipping',
            (string)Product::PRODUCT_TYPE_EXEMPT => 'exempt',
            (string)Product::PRODUCT_TYPE_REDUCED_TAX => 'reduced_tax',
            (string)Product::PRODUCT_TYPE_OVERRIDE_TAX => 'override_tax',
            (string)Product::PRODUCT_TYPE_ZERO_RATED => 'zero_rated',
            (string)Product::PRODUCT_TYPE_REVERSE_TAX => 'reverse_tax',
            (string)Product::PRODUCT_INTRA_COMMUNITY => 'intra_community',
            default => null,
        };
    }

    public function toArray(): array
    {
        return [
            'physical' => $this->physical,
            'service' => $this->service,
            'digital' => $this->digital,
            'shipping' => $this->shipping,
            'exempt' => $this->exempt,
            'reduced_tax' => $this->reduced_tax,
            'override_tax' => $this->override_tax,
            'zero_rated' => $this->zero_rated,
            'reverse_tax' => $this->reverse_tax,
            'intra_community' => $this->intra_community,
        ];
    }
}
