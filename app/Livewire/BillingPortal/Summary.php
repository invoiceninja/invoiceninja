<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal;

use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Utils\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Summary extends Component
{
    public Subscription $subscription;

    public array $context;

    public function mount()
    {
        $bundle = $this->context['bundle'] ?? [
            'recurring_products' => [],
            'optional_recurring_products' => [],
            'one_time_products' => [],
            'optional_one_time_products' => [],
        ];

        foreach ($this->subscription->service()->recurring_products() as $key => $product) {
            $bundle['recurring_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['recurring_products'][$product->hashed_id]['quantity'] ?? 1,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['recurring_products'][$product->hashed_id]['product']['is_recurring'] = true;
        }

        foreach ($this->subscription->service()->products() as $key => $product) {
            $bundle['one_time_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['one_time_products'][$product->hashed_id]['quantity'] ?? 1,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['one_time_products'][$product->hashed_id]['product']['is_recurring'] = false;
        }

        foreach ($this->subscription->service()->optional_recurring_products() as $key => $product) {
            $bundle['optional_recurring_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['optional_recurring_products'][$product->hashed_id]['quantity'] ?? 0,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['optional_recurring_products'][$product->hashed_id]['product']['is_recurring'] = true;
        }

        foreach ($this->subscription->service()->optional_products() as $key => $product) {
            $bundle['optional_one_time_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['optional_one_time_products'][$product->hashed_id]['quantity'] ?? 0,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['optional_one_time_products'][$product->hashed_id]['product']['is_recurring'] = false;
        }

        $this->dispatch('purchase.context', property: 'bundle', value: $bundle);
    }

    public function oneTimePurchasesTotal(bool $raw = false)
    {
        if (isset($this->context['bundle']['recurring_products']) === false) {
            return 0;
        }

        $one_time = collect($this->context['bundle']['one_time_products'])->sum(function ($item) {
            return $item['product']['price'] * $item['quantity'];
        });

        $one_time_optional = collect($this->context['bundle']['optional_one_time_products'])->sum(function ($item) {
            return $item['product']['price'] * $item['quantity'];
        });

        if ($raw) {
            return $one_time + $one_time_optional;
        }

        return Number::formatMoney($one_time + $one_time_optional, $this->subscription->company);

    }

    public function recurringPurchasesTotal(bool $raw = false)
    {
        if (isset($this->context['bundle']['recurring_products']) === false) {
            return 0;
        }

        $recurring = collect($this->context['bundle']['recurring_products'])->sum(function ($item) {
            return $item['product']['price'] * $item['quantity'];
        });

        $recurring_optional = collect($this->context['bundle']['optional_recurring_products'])->sum(function ($item) {
            return $item['product']['price'] * $item['quantity'];
        });

        if ($raw) {
            return $recurring + $recurring_optional;
        }

        return \sprintf(
            '%s/%s',
            Number::formatMoney($recurring + $recurring_optional, $this->subscription->company),
            RecurringInvoice::frequencyForKey($this->subscription->frequency_id)
        );
    }

    #[Computed()]
    public function total()
    {
        return Number::formatMoney(
            collect([
                $this->oneTimePurchasesTotal(raw: true),
                $this->recurringPurchasesTotal(raw: true),
            ])->sum(),
            $this->subscription->company
        );
    }

    public function items()
    {
        if (isset($this->context['bundle']) === false) {
            return [];
        }

        $products = [];

        foreach ($this->context['bundle']['recurring_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription->company) . ' / ' . RecurringInvoice::frequencyForKey($this->subscription->frequency_id),
            ];
        }

        foreach ($this->context['bundle']['optional_recurring_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription->company) . ' / ' . RecurringInvoice::frequencyForKey($this->subscription->frequency_id),
            ];
        }

        foreach ($this->context['bundle']['one_time_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription->company),
            ];
        }

        foreach ($this->context['bundle']['optional_one_time_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription->company),
            ];
        }

        $this->dispatch('purchase.context', property: 'products', value: $products);

        return $products;
    }

    public function render()
    {
        return view('billing-portal.v3.summary');
    }
}
