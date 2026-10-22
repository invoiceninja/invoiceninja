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

namespace App\Services\Product;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Utils\Number;

/**
 * Applies product price tiers (quantity based pricing) to the
 * line items of an entity (invoice, quote, credit, recurring invoice).
 *
 * A product may define `price_tiers` as a JSON array such as:
 *
 *     [
 *         {"min_quantity": 10,  "price": 9.5},
 *         {"min_quantity": 50,  "discount": 12.5},
 *         {"min_quantity": 100, "price": 7.0}
 *     ]
 *
 * How the tiers are resolved depends on the product's `price_tiers_mode`:
 *
 *  - `all_units`   every unit is billed at the highest tier satisfying
 *                  `quantity >= min_quantity`. A `price` tier sets the line
 *                  item `cost` directly, a `discount` tier sets the line
 *                  item `discount` (percent) and unsets `is_amount_discount`.
 *  - `progressive` each bracket is billed at its own rate (the first units
 *                  up to the first tier use the base price, the following
 *                  brackets use the tier rates). The line item `cost` is set
 *                  to the resulting blended unit price. A `discount` tier is
 *                  converted into a bracket rate relative to the base price.
 *
 * A manually entered discount on the line item always takes precedence and
 * is never overwritten. A line item may opt out by setting
 * `use_price_tiers` to `false`.
 *
 * Whenever a tier is applied, a localized note describing the pricing is
 * appended to the line item's `notes` so it appears on the document. On
 * every run previously generated notes are removed first, so notes stay
 * accurate when quantities or tiers change.
 */
class ApplyPriceTiers
{
    private Company|Client $entity;

    private string $locale = 'en';

    /**
     * @param  array<object>|null  $line_items
     * @return array<object>|null
     */
    public function run(?array $line_items, Company $company, ?Client $client = null): ?array
    {
        if (! $company->enable_price_tiers || ! is_array($line_items) || count($line_items) === 0) {
            return $line_items;
        }

        $this->entity = $client ?? $company;
        $this->locale = $client?->locale() ?? $company->locale() ?? 'en';

        $products = Product::query()
            ->where('company_id', $company->id)
            ->whereIn('product_key', $this->resolveProductKeys($line_items))
            ->whereNotNull('price_tiers')
            ->get()
            ->keyBy('product_key');

        foreach ($line_items as $item) {
            $this->stripTierNote($item);

            if (! $this->isTierable($item)) {
                continue;
            }

            /** @var Product|null $product */
            $product = $products->get($item->product_key);

            if (! $product) {
                continue;
            }

            $quantity = (float) ($item->quantity ?? 0);

            if (($product->price_tiers_mode ?? 'all_units') === 'progressive') {
                $cost = $this->progressiveCost($product, $quantity);

                if ($cost !== null) {
                    $item->cost = $cost;
                    $this->appendTierNote($item, $this->progressiveNote($product));
                }

                continue;
            }

            $tier = $product->priceTierForQuantity($quantity);

            if (! $tier) {
                continue;
            }

            if (isset($tier['price'])) {
                $item->cost = (float) $tier['price'];
                $this->appendTierNote($item, ctrans('texts.price_tier_note', [
                    'qty' => (float) $tier['min_quantity'],
                    'rate' => Number::formatMoney((float) $tier['price'], $this->entity),
                ], $this->locale));
            } elseif (! isset($item->use_price_tiers) && (float) ($item->discount ?? 0) > 0) {
                continue;
            } else {
                $item->discount = (float) $tier['discount'];
                $item->is_amount_discount = false;
                $this->appendTierNote($item, ctrans('texts.discount_tier_note', [
                    'qty' => (float) $tier['min_quantity'],
                    'discount' => (float) $tier['discount'],
                ], $this->locale));
            }
        }

        return $line_items;
    }

    /**
     * Blended unit price under progressive (bracket) pricing: every
     * quantity bracket between two `min_quantity` boundaries is billed at
     * the rate of the preceding tier.
     */
    private function progressiveCost(Product $product, float $quantity): ?float
    {
        if ($quantity <= 0) {
            return null;
        }

        $base_price = (float) $product->price;

        $total = 0.0;
        $lower = 0.0;
        $rate = $base_price;

        foreach ($product->sortedPriceTiers() as $tier) {
            /* A tier applies to every unit from `min_quantity` onward, so
             * the bracket boundary sits one unit before it */
            $boundary = max(0, (float) $tier['min_quantity'] - 1);

            /* Tiers above the requested quantity are not reached */
            if ($boundary >= $quantity) {
                break;
            }

            /* Close the current bracket at the previous rate */
            if ($boundary > $lower) {
                $total += ($boundary - $lower) * $rate;
                $lower = $boundary;
            }

            $rate = isset($tier['price'])
                ? (float) $tier['price']
                : $base_price * (1 - (float) ($tier['discount'] ?? 0) / 100);
        }

        $total += ($quantity - $lower) * $rate;

        return Number::roundValue($total / $quantity, 4);
    }

    /**
     * Builds the bracket description for progressive pricing,
     * e.g. "1–9 @ €4.00 · 10–49 @ €3.60 · 50+ @ €3.20".
     */
    private function progressiveNote(Product $product): string
    {
        $base_price = (float) $product->price;

        $tiers = $product->sortedPriceTiers()->values();

        $parts = [];

        if ($tiers->isNotEmpty() && (float) $tiers->first()['min_quantity'] > 1) {
            $parts[] = '1–' . ((float) $tiers->first()['min_quantity'] - 1)
                . ' @ ' . Number::formatMoney($base_price, $this->entity);
        }

        foreach ($tiers as $index => $tier) {
            $hi = isset($tiers[$index + 1])
                ? '–' . ((float) $tiers[$index + 1]['min_quantity'] - 1)
                : '+';

            $rate = isset($tier['price'])
                ? (float) $tier['price']
                : $base_price * (1 - (float) ($tier['discount'] ?? 0) / 100);

            $parts[] = (float) $tier['min_quantity'] . $hi
                . ' @ ' . Number::formatMoney($rate, $this->entity);
        }

        return ctrans('texts.progressive_tier_note', [
            'tiers' => implode(' · ', $parts),
        ], $this->locale);
    }

    /**
     * Removes previously generated tier notes so the line item never
     * accumulates stale pricing descriptions across re-saves.
     */
    private function stripTierNote(object $item): void
    {
        $notes = $item->notes ?? '';

        if (! is_string($notes) || $notes === '') {
            return;
        }

        $prefixes = array_map(function ($key) {
            $template = ctrans("texts.{$key}", [], $this->locale);

            $pos = strpos($template, ':');

            return $pos === false ? $template : substr($template, 0, $pos);
        }, ['price_tier_note', 'discount_tier_note', 'progressive_tier_note']);

        $lines = preg_split('/\r?\n/', $notes) ?: [];

        $kept = array_filter($lines, function ($line) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                if ($prefix !== '' && str_starts_with(ltrim($line), rtrim($prefix))) {
                    return false;
                }
            }

            return true;
        });

        $item->notes = rtrim(implode("\n", $kept));
    }

    private function appendTierNote(object $item, string $note): void
    {
        $existing = trim($item->notes ?? '');

        $item->notes = $existing === '' ? $note : "{$existing}\n{$note}";
    }

    /**
     * @param  array<object>  $line_items
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function resolveProductKeys(array $line_items)
    {
        return collect($line_items)
            ->filter(function ($item) {
                return $this->isTierable($item);
            })
            ->pluck('product_key')
            ->unique()
            ->values();
    }

    private function isTierable(object $item): bool
    {
        if (($item->type_id ?? '1') != '1' || empty($item->product_key)) {
            return false;
        }

        return ($item->use_price_tiers ?? true) !== false;
    }
}
