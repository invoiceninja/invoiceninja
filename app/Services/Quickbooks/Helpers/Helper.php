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

namespace App\Services\Quickbooks\Helpers;

use App\Models\Company;
use App\Services\Quickbooks\QuickbooksService;
use App\Utils\BcMath;

/**
 * Helper class for QuickBooks invoice-related business logic.
 * This class handles non-transformation logic such as API lookups, account resolution, etc.
 */
class Helper
{
    public function __construct(
        private Company $company,
        private QuickbooksService $qb_service
    ) {}

    /**
     * Find a Tax Rate in QuickBooks by name or rate.
     * TaxRates are read-only in QuickBooks and cannot be created via API.
     *
     * @param float $tax_rate The tax rate percentage
     * @param string $tax_name The tax name
     * @return string|null The QuickBooks Tax Rate ID, or null if not found
     */
    public function findTaxRate(float $tax_rate, string $tax_name): ?string
    {

        $tax_rates = $this->company->quickbooks->settings->tax_rate_map ?? [];

        $tax_rate_id = null;

        foreach ($tax_rates as $tax) {
            if (BcMath::equal($tax['rate'], $tax_rate) && $tax['name'] == $tax_name) {
                $tax_rate_id = $tax['id'];
            }
        }

        return $tax_rate_id;
    }

    /**
     * Get a discount account ID from QuickBooks.
     * Looks for an account with AccountType 'Income' and name containing 'Discount'.
     *
     * @return string|null The discount account ID, or null if not found
     */
    public function getDiscountAccountId(): ?string
    {
        try {
            // Query for discount accounts (typically named "Discounts Given" or similar)
            $query = "SELECT * FROM Account WHERE AccountType = 'Income' AND Active = true";
            $accounts = $this->qb_service->sdk->Query($query);

            if (!empty($accounts)) {
                // Look for account with "Discount" in the name
                foreach ($accounts as $account) {
                    $name = is_object($account) ? ($account->Name ?? '') : ($account['Name'] ?? '');
                    if (stripos($name, 'discount') !== false) {
                        $id = is_object($account) ? ($account->Id ?? null) : ($account['Id'] ?? null);
                        if ($id) {
                            return (string) $id;
                        }
                    }
                }

                // If no discount account found, use first income account as fallback
                $first_account = $accounts[0];
                $id = is_object($first_account) ? ($first_account->Id ?? null) : ($first_account['Id'] ?? null);
                if ($id) {
                    return (string) $id;
                }
            }
        } catch (\Exception $e) {
            nlog("QuickBooks: Error fetching discount account: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Get an income account ID from stored QuickBooks settings or fallback to querying.
     *
     * Priority:
     * 1. Use company->quickbooks->settings->qb_income_account_id if set
     * 2. Use first account from company->quickbooks->settings->income_account_map
     * 3. Fallback to querying QuickBooks API
     *
     * @return string|null The income account ID, or null if not found
     */
    public function getIncomeAccountId(): ?string
    {
        // First, check if default income account ID is set in settings
        $default_account_id = $this->company->quickbooks->settings->qb_income_account_id ?? null;
        if (!empty($default_account_id)) {
            return (string) $default_account_id;
        }

        // Second, check income_account_map and use the first account
        $income_account_map = $this->company->quickbooks->settings->income_account_map ?? [];
        if (!empty($income_account_map) && isset($income_account_map[0]['id'])) {
            return (string) $income_account_map[0]['id'];
        }

        // Fallback: Query QuickBooks API if no stored settings available
        try {
            $query = "SELECT * FROM Account WHERE AccountType = 'Income' AND Active = true MAXRESULTS 1";
            $accounts = $this->qb_service->sdk->Query($query);

            // QB SDK can return a single object or an array; normalize to array
            if (!empty($accounts) && !is_array($accounts)) { // @phpstan-ignore-line
                $accounts = [$accounts];
            }
            if (!empty($accounts) && isset($accounts[0])) {
                $account = $accounts[0];
                $account_id = data_get($account, 'Id') ?? data_get($account, 'Id.value');
                return $account_id ? (string) $account_id : null;
            }

            return null;
        } catch (\Exception $e) {
            nlog("QuickBooks: Error fetching income account: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Clean HTML text by replacing <br> tags with newlines and stripping all HTML tags.
     *
     * @param string $text The text to clean
     * @return string The cleaned text
     */
    public function cleanHtmlText(string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // Replace <br> and <br/> tags (case insensitive) with newlines
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);

        // Strip all remaining HTML tags
        $text = strip_tags($text);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Clean up multiple consecutive newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Calculate total tax from QuickBooks data and create Tax Rate if needed.
     * This is business logic that creates/updates Tax Rate models.
     *
     * @param mixed $qb_data QuickBooks invoice data
     * @return array [tax_rate, tax_name]
     */
    public function calculateTotalTax(mixed $qb_data): array
    {
        $total_tax = data_get($qb_data, 'TxnTaxDetail.TotalTax', false);

        $tax_rate = 0;
        $tax_name = '';

        if ($total_tax == "0") {
            return [$tax_rate, $tax_name];
        }

        $taxLines = data_get($qb_data, 'TxnTaxDetail.TaxLine', []) ?? [];

        // QB can return a single IPPLine object or an array of lines; normalize to array
        if (!empty($taxLines)) {
            if (!is_array($taxLines)) {
                $taxLines = [$taxLines];
            } elseif (!isset($taxLines[0])) {
                $taxLines = [$taxLines];
            }
        }

        $totalTaxRate = 0;

        foreach ($taxLines as $taxLine) {
            $taxRate = data_get($taxLine, 'TaxLineDetail.TaxPercent', 0);
            $totalTaxRate += $taxRate;
        }

        if ($totalTaxRate > 0) {
            $formattedTaxRate = rtrim(rtrim(number_format($totalTaxRate, 6), '0'), '.');
            $formattedTaxRate = trim($formattedTaxRate);

            $tr = \App\Models\TaxRate::firstOrNew(
                [
                    'company_id' => $this->company->id,
                    'rate' => $formattedTaxRate,
                ],
                [
                    'name' => "Sales Tax [{$formattedTaxRate}]",
                    'rate' => $formattedTaxRate,
                ]
            );
            $tr->company_id = $this->company->id;
            $tr->user_id = $this->company->owner()->id;
            $tr->save();

            $tax_rate = $tr->rate;
            $tax_name = $tr->name;
        }

        return [$tax_rate, $tax_name];
    }

    /**
     * Check if discount is applied after tax and handle company custom field.
     * This is business logic that modifies company settings.
     *
     * @param mixed $qb_data QuickBooks invoice data
     * @return float The discount amount (positive) or 0
     */
    public function checkIfDiscountAfterTax(mixed $qb_data): float
    {
        if (data_get($qb_data, 'ApplyTaxAfterDiscount') == 'true') {
            return 0;
        }

        foreach (data_get($qb_data, 'Line', []) as $line) {
            if (data_get($line, 'DetailType') == 'DiscountLineDetail') {
                if (!isset($this->company->custom_fields->surcharge1)) {
                    $this->company->custom_fields->surcharge1 = ctrans('texts.discount');
                    $this->company->save();
                }

                return (float) data_get($line, 'Amount', 0) * -1;
            }
        }

        return 0;
    }

    /**
     * Split a tax name string into the name component and percentage component.
     * Handles formats like "New York State 4%" -> ["New York State", "4%"]
     *
     * @param string $tax_name The tax name string (e.g., "New York State 4%")
     * @return array{name: string, percentage: string}|null Returns array with 'name' and 'percentage' keys, or null if no percentage found
     */
    public function splitTaxName(string $tax_name): ?array
    {
        if (empty($tax_name)) {
            return null;
        }

        // Pattern to match a number (with optional decimals) followed by a % sign
        // Matches: "4%", "4.5%", "8.75%", etc.
        if (preg_match('/^(.+?)\s+(\d+\.?\d*\s*%)$/', $tax_name, $matches)) {
            return [
                'name' => trim($matches[1]),
                'percentage' => trim($matches[2]),
            ];
        }

        // If the string is just a percentage (e.g., "4%"), return empty name
        if (preg_match('/^(\d+\.?\d*\s*%)$/', $tax_name, $matches)) {
            return [
                'name' => '',
                'percentage' => str_replace('%', '', trim($matches[1])),
            ];
        }

        return null;
    }

    /**
     * Get payments from QuickBooks invoice data.
     *
     * @param mixed $qb_data QuickBooks invoice data
     * @return array Array of payment transaction IDs
     */
    public function getPayments(mixed $qb_data): array
    {
        $payments = [];
        $qb_payments = data_get($qb_data, 'LinkedTxn', []);

        if ($qb_payments === false || $qb_payments === null) {
            $qb_payments = [];
        }

        // QB can return a single object or an array; normalize to array
        if (!empty($qb_payments)) {
            if (!is_array($qb_payments)) {
                $qb_payments = [$qb_payments];
            } elseif (!isset($qb_payments[0])) {
                $qb_payments = [$qb_payments];
            }
        }

        foreach ($qb_payments as $payment) {
            if (data_get($payment, 'TxnType', false) == 'Payment') {
                $payments[] = data_get($payment, 'TxnId', false);
            }
        }
        return $payments;
    }

    /**
     * Get line items from QuickBooks invoice data.
     *
     * @param mixed $qb_data QuickBooks invoice data
     * @param array $tax_array [tax_rate, tax_name]
     * @return array Array of InvoiceItem objects
     */
    public function getLineItems(mixed $qb_data, array $tax_array): array
    {

        $qb_items = data_get($qb_data, 'Line', []);
        $include_discount = data_get($qb_data, 'ApplyTaxAfterDiscount', 'true');
        $items = [];

        // QB can return a single IPPLine object or an array; normalize to array
        if (!empty($qb_items)) {
            if (!is_array($qb_items)) {
                $qb_items = [$qb_items];
            } elseif (!isset($qb_items[0])) {
                $qb_items = [$qb_items];
            }
        }

        if (!empty($qb_items) && !isset($qb_items[0])) {
            $tax_rate = (float) data_get($qb_data, 'TxnTaxDetail.TaxLine.TaxLineDetail.TaxPercent', 0);
            $tax_name = $tax_rate > 0 ? "Sales Tax [{$tax_rate}]" : '';

            $item = new \App\DataMapper\InvoiceItem();
            $item->product_key = '';
            $item->notes = 'Recurring Charge';
            $item->quantity = 1;
            $item->cost = (float) data_get($qb_items, 'Amount', 0);
            $item->discount = 0;
            $item->is_amount_discount = false;
            $item->type_id = '1';
            $item->tax_id = '1';
            $item->tax_rate1 = (float) $tax_rate;
            $item->tax_name1 = $tax_name;
            $items[] = (object) $item;
            return $items;
        }

        foreach ($qb_items as $qb_item) {
            $taxCodeRef = data_get($qb_item, 'TaxCodeRef', data_get($qb_item, 'SalesItemLineDetail.TaxCodeRef', 'TAX'));

            if (data_get($qb_item, 'DetailType') == 'SalesItemLineDetail') {
                $item = new \App\DataMapper\InvoiceItem();
                $item->product_key = data_get($qb_item, 'SalesItemLineDetail.ItemRef.name', '');
                $item->notes = data_get($qb_item, 'Description', '');
                $item->quantity = (float) (data_get($qb_item, 'SalesItemLineDetail.Qty') ?? 1);
                $item->cost = (float) (data_get($qb_item, 'SalesItemLineDetail.UnitPrice') ?? data_get($qb_item, 'SalesItemLineDetail.MarkupInfo.Value', 0));
                $item->discount = (float) data_get($item, 'DiscountRate', data_get($qb_item, 'DiscountAmount', 0));
                $item->is_amount_discount = data_get($qb_item, 'DiscountAmount', 0) > 0 ? true : false;
                $item->type_id = stripos(data_get($qb_item, 'ItemAccountRef.name') ?? '', 'Service') !== false ? '2' : '1';
                $item->tax_id = $taxCodeRef == 'NON' ? (string) \App\Models\Product::PRODUCT_TYPE_EXEMPT : $item->type_id;
                $item->tax_rate1 = $taxCodeRef == 'NON' ? 0 : (float) $tax_array[0];
                $item->tax_name1 = $taxCodeRef == 'NON' ? '' : $tax_array[1];
                $items[] = (object) $item;
            }

            if (data_get($qb_item, 'DetailType') == 'DiscountLineDetail' && $include_discount == 'true') {
                $item = new \App\DataMapper\InvoiceItem();
                $item->product_key = ctrans('texts.discount');
                $item->notes = ctrans('texts.discount');
                $item->quantity = 1;
                $item->cost = (float) data_get($qb_item, 'Amount', 0) * -1;
                $item->discount = 0;
                $item->is_amount_discount = true;
                $item->tax_rate1 = $include_discount == 'true' ? (float) $tax_array[0] : 0;
                $item->tax_name1 = $include_discount == 'true' ? $tax_array[1] : '';
                $item->type_id = '1';
                $item->tax_id = (string) \App\Models\Product::PRODUCT_TYPE_PHYSICAL;
                $items[] = (object) $item;
            }
        }


        return $items;
    }
}
