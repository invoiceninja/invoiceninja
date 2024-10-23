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

namespace App\Services\SubscriptionV4;

class SummaryService
{
    public function __construct(
        public array $context
    )
    {}

    public function recurringPurchasesTotal(): int
    {
        $recurring = collect($this->context['recurring_products'])->sum(fn ($item) => $item['price'] * $item['bundle']['quantity']);
        $recurring_optional = collect($this->context['optional_recurring_products'])->sum(fn ($item) => $item['price'] * $item['bundle']['quantity']);
        
        return $recurring + $recurring_optional;
    }

    public function oneTimePurchasesTotal(): int
    {
        $one_time = collect($this->context['products'])->sum(fn ($item) => $item['price'] * $item['bundle']['quantity']);
        $one_time_optional = collect($this->context['optional_products'])->sum(fn ($item) => $item['price'] * $item['bundle']['quantity']);

        return $one_time + $one_time_optional;
    }
}