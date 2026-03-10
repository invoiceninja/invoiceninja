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

namespace App\DataMapper;

use App\Models\Product;

/**
 * QuickbooksSync.
 *
 * Product type to income account mapping:
 * Keys are Product::PRODUCT_TYPE_* constants (int). Values are QuickBooks account IDs (string|null).
 * Example: [Product::PRODUCT_TYPE_SERVICE => '123', Product::PRODUCT_TYPE_PHYSICAL => '456']
 * Null values indicate the account has not been configured for that product type.
 */
class QuickbooksSync
{
    public QuickbooksSyncMap $client;

    public QuickbooksSyncMap $vendor;

    public QuickbooksSyncMap $invoice;

    public QuickbooksSyncMap $sales;

    public QuickbooksSyncMap $quote;

    public QuickbooksSyncMap $purchase_order;

    public QuickbooksSyncMap $product;

    public QuickbooksSyncMap $payment;

    public QuickbooksSyncMap $expense;

    public QuickbooksSyncMap $expense_category;

    /**
     * QuickBooks income account ID per product type.
     * Use getAccountId(int $productTypeId) or the typed properties (physical, service, etc.).
     */
    public array $income_account_map;

    public array $tax_rate_map;

    public ?string $qb_income_account_id = null;

    public bool $automatic_taxes = false;

    public ?string $default_taxable_code = null;

    public ?string $default_exempt_code = null;

    public ?string $country = null;

    public array $payment_method_map = [];

    public function __construct(array $attributes = [])
    {
        $this->client = new QuickbooksSyncMap($attributes['client'] ?? []);
        $this->vendor = new QuickbooksSyncMap($attributes['vendor'] ?? []);
        $this->invoice = new QuickbooksSyncMap($attributes['invoice'] ?? []);
        $this->sales = new QuickbooksSyncMap($attributes['sales'] ?? []);
        $this->quote = new QuickbooksSyncMap($attributes['quote'] ?? []);
        $this->purchase_order = new QuickbooksSyncMap($attributes['purchase_order'] ?? []);
        $this->product = new QuickbooksSyncMap($attributes['product'] ?? []);
        $this->payment = new QuickbooksSyncMap($attributes['payment'] ?? []);
        $this->expense = new QuickbooksSyncMap($attributes['expense'] ?? []);
        $this->expense_category = new QuickbooksSyncMap($attributes['expense_category'] ?? []);
        $this->income_account_map = $attributes['income_account_map'] ?? [];
        $this->qb_income_account_id = $attributes['qb_income_account_id'] ?? null;
        $this->tax_rate_map = $attributes['tax_rate_map'] ?? [];
        $this->automatic_taxes = $attributes['automatic_taxes'] ?? false; //requires us to syncronously push the invoice to QB, and return fully formed Invoice with taxes included.
        $this->default_taxable_code = $attributes['default_taxable_code'] ?? null;
        $this->default_exempt_code = $attributes['default_exempt_code'] ?? null;
        $this->country = $attributes['country'] ?? null;
        $this->payment_method_map = $attributes['payment_method_map'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'client' => $this->client->toArray(),
            'vendor' => $this->vendor->toArray(),
            'invoice' => $this->invoice->toArray(),
            'sales' => $this->sales->toArray(),
            'quote' => $this->quote->toArray(),
            'purchase_order' => $this->purchase_order->toArray(),
            'product' => $this->product->toArray(),
            'payment' => $this->payment->toArray(),
            'expense' => $this->expense->toArray(),
            'expense_category' => $this->expense_category->toArray(),
            'income_account_map' => $this->income_account_map,
            'qb_income_account_id' => $this->qb_income_account_id,
            'tax_rate_map' => $this->tax_rate_map,
            'automatic_taxes' => $this->automatic_taxes,
            'default_taxable_code' => $this->default_taxable_code,
            'default_exempt_code' => $this->default_exempt_code,
            'country' => $this->country,
            'payment_method_map' => $this->payment_method_map,
        ];
    }
}
