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

namespace App\Services\Quickbooks\Invoice;

use App\Models\Invoice;
use App\Models\TaxRate;
use App\Services\Quickbooks\QuickbooksService;
use App\Utils\BcMath;

class AstTaxResponseProcessor
{
    public function __construct(private QuickbooksService $service)
    {
    }

    public function process(mixed $qb_response, Invoice $invoice): void
    {
        try {
            $use_ast = $this->service->company->quickbooks->settings->automatic_taxes ?? false;

            if (!$use_ast) {
                return;
            }

            $balance_before = (float) $invoice->balance;
            $txn_tax_detail = data_get($qb_response, 'TxnTaxDetail');

            if (!$txn_tax_detail) {
                nlog("QuickBooks: No TxnTaxDetail found in response for invoice {$invoice->id}");
                return;
            }

            $total_tax = (float) data_get($txn_tax_detail, 'TotalTax', 0);
            $tax_lines = data_get($txn_tax_detail, 'TaxLine', []);

            if (!empty($tax_lines)) {
                if (!is_array($tax_lines)) {
                    $tax_lines = [$tax_lines];
                } elseif (!isset($tax_lines[0])) {
                    $tax_lines = [$tax_lines];
                }
            }

            if (empty($tax_lines) || $total_tax <= 0) {
                $this->clearAllTaxes($invoice);
                return;
            }

            $qb_line_items = data_get($qb_response, 'Line', []);
            if (!empty($qb_line_items)) {
                if (!is_array($qb_line_items)) {
                    $qb_line_items = [$qb_line_items];
                } elseif (!isset($qb_line_items[0])) {
                    $qb_line_items = [$qb_line_items];
                }
            }

            if (!empty($qb_line_items)) {
                $this->processLineItemTaxes($qb_line_items, $invoice, $tax_lines);
            } else {
                $invoice->tax_name1 = '';
                $invoice->tax_rate1 = 0;
                $invoice->tax_name2 = '';
                $invoice->tax_rate2 = 0;
                $invoice->tax_name3 = '';
                $invoice->tax_rate3 = 0;
            }

            $invoice->saveQuietly();
            $invoice = $invoice->calc()->getInvoice();

            $this->validateAndSyncAmounts($qb_response, $invoice);
            $this->applyAstClientBalanceAdjustment($invoice, $balance_before);
            $invoice->saveQuietly();
        } catch (\Exception $e) {
            nlog("QuickBooks: Error processing tax response for invoice {$invoice->id}: {$e->getMessage()}");
        }
    }

    private function applyAstClientBalanceAdjustment(Invoice $invoice, float $balance_before): void
    {
        if ($invoice->status_id === Invoice::STATUS_DRAFT) {
            return;
        }

        if (! in_array($invoice->status_id, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL], true)) {
            return;
        }

        $invoice = $invoice->calc()->getInvoice();
        $balance_delta = BcMath::sub((float) $invoice->balance, $balance_before, 2);

        if (BcMath::isZero($balance_delta)) {
            return;
        }

        $invoice->loadMissing('client');
        $invoice->client->service()->updateBalance((float) $balance_delta);
        $invoice->ledger()->updateInvoiceBalance(
            (float) $balance_delta,
            "QuickBooks AST adjustment for invoice {$invoice->number}"
        );
    }

    /**
     * @param  array<int, mixed>  $qb_line_items
     * @param  array<int, mixed>  $tax_lines
     */
    private function processLineItemTaxes(array $qb_line_items, Invoice $invoice, array $tax_lines): void
    {
        $tax_rate_map = $this->service->company->quickbooks->settings->tax_rate_map ?? [];
        $tax_rate_map_by_id = collect($tax_rate_map)->keyBy('id')->toArray();
        $line_items = $invoice->line_items;
        $line_item_index = 0;
        $line_items_modified = false;

        foreach ($qb_line_items as $qb_line_item) {
            if (data_get($qb_line_item, 'DetailType') !== 'SalesItemLineDetail') {
                continue;
            }

            if ($line_item_index >= count($line_items)) {
                break;
            }

            $line_item = $line_items[$line_item_index];
            $tax_code_ref = data_get($qb_line_item, 'SalesItemLineDetail.TaxCodeRef.value')
                          ?? data_get($qb_line_item, 'SalesItemLineDetail.TaxCodeRef')
                          ?? data_get($qb_line_item, 'TaxCodeRef.value')
                          ?? data_get($qb_line_item, 'TaxCodeRef');

            if ($tax_code_ref === 'NON' || empty($tax_code_ref)) {
                $line_item->tax_name1 = '';
                $line_item->tax_rate1 = 0;
                $line_item->tax_name2 = '';
                $line_item->tax_rate2 = 0;
                $line_item->tax_name3 = '';
                $line_item->tax_rate3 = 0;
                $line_items_modified = true;
                $line_item_index++;
                continue;
            }

            $line_tax_detail = data_get($qb_line_item, 'TaxLineDetail');

            if ($line_tax_detail) {
                $this->assignTaxesToLineItem($line_item, [$line_tax_detail], $tax_rate_map_by_id, $invoice);
                $line_items_modified = true;
            } elseif (!empty($tax_lines)) {
                $this->assignTaxesToLineItem($line_item, $tax_lines, $tax_rate_map_by_id, $invoice);
                $line_items_modified = true;
            }

            $line_item_index++;
        }

        if ($line_items_modified) {
            $invoice->line_items = $line_items;
        }

        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name3 = '';
        $invoice->tax_rate3 = 0;
    }

    /**
     * @param  array<int, mixed>  $tax_details
     * @param  array<string, mixed>  $tax_rate_map_by_id
     */
    private function assignTaxesToLineItem(object $line_item, array $tax_details, array $tax_rate_map_by_id, ?Invoice $invoice = null): void
    {
        if (!empty($tax_details)) {
            if (!is_array($tax_details)) {
                $tax_details = [$tax_details];
            } elseif (!isset($tax_details[0])) {
                $tax_details = [$tax_details];
            }
        }

        $this->aggregateTaxesForLineItem($line_item, $tax_details, $tax_rate_map_by_id, $invoice);
    }

    /**
     * @param  array<int, mixed>  $tax_details
     * @param  array<string, mixed>  $tax_rate_map_by_id
     */
    private function aggregateTaxesForLineItem(object $line_item, array $tax_details, array $tax_rate_map_by_id, ?Invoice $invoice = null): void
    {
        $aggregated_rate = $this->calculateAggregatedTaxRate($tax_details, true);
        $tax_name = $this->formatTaxName($aggregated_rate, $invoice);

        $this->createTaxRateIfNeeded($tax_name, $aggregated_rate);
        $this->assignTaxToEntity($line_item, $tax_name, $aggregated_rate);
    }

    /**
     * @param  array<int, mixed>  $tax_items
     */
    private function calculateAggregatedTaxRate(array $tax_items, bool $handle_nested = false): float
    {
        $total_tax_percent = 0;
        $total_tax_amount = 0;

        foreach ($tax_items as $tax_item) {
            $tax_line_detail = $handle_nested
                ? (data_get($tax_item, 'TaxLineDetail') ?? $tax_item)
                : data_get($tax_item, 'TaxLineDetail');

            $tax_percent = (float) data_get($tax_line_detail, 'TaxPercent', 0);
            $tax_amount = (float) data_get($tax_item, 'Amount', 0);

            if ($tax_percent > 0) {
                $total_tax_percent += $tax_percent;
                $total_tax_amount += $tax_amount;
            }
        }

        $aggregated_rate = $total_tax_percent > 0 ? $total_tax_percent : 0;

        if ($aggregated_rate == 0 && $total_tax_amount > 0) {
            $first_item = $tax_items[0] ?? null;
            if ($first_item) {
                $net_amount_taxable = (float) data_get($first_item, 'TaxLineDetail.NetAmountTaxable', 0);
                if ($net_amount_taxable > 0) {
                    $aggregated_rate = ($total_tax_amount / $net_amount_taxable) * 100;
                }
            }
        }

        return $aggregated_rate;
    }

    private function formatTaxName(float $rate, ?Invoice $invoice = null): string
    {
        $state = '';
        if ($invoice) {
            $state = trim($invoice->client->state ?? '');
        }

        return !empty($state)
            ? "{$state}"
            : "{$rate}%";
    }

    private function assignTaxToEntity($entity, string $tax_name, float $tax_rate): void
    {
        $entity->tax_name1 = $tax_name;
        $entity->tax_rate1 = round($tax_rate, 2);
        $entity->tax_name2 = '';
        $entity->tax_rate2 = 0;
        $entity->tax_name3 = '';
        $entity->tax_rate3 = 0;
    }

    private function createTaxRateIfNeeded(string $tax_name, float $tax_rate): void
    {
        if ($tax_rate <= 0) {
            return;
        }

        $ninja_tax_rate = TaxRate::firstOrNew([
            'company_id' => $this->service->company->id,
            'name' => $tax_name,
            'rate' => $tax_rate,
        ]);

        $ninja_tax_rate->company_id = $this->service->company->id;
        $ninja_tax_rate->name = $tax_name;
        $ninja_tax_rate->rate = $tax_rate;

        if (!$ninja_tax_rate->exists) {
            $ninja_tax_rate->user_id = $this->service->company->owner()->id;
            $ninja_tax_rate->save();
        }
    }

    private function clearAllTaxes(Invoice $invoice): void
    {
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name3 = '';
        $invoice->tax_rate3 = 0;

        $line_items = $invoice->line_items;
        foreach ($line_items as $line_item) {
            $line_item->tax_name1 = '';
            $line_item->tax_rate1 = 0;
            $line_item->tax_name2 = '';
            $line_item->tax_rate2 = 0;
            $line_item->tax_name3 = '';
            $line_item->tax_rate3 = 0;
        }
        $invoice->line_items = $line_items;
        $invoice->saveQuietly();
    }

    private function validateAndSyncAmounts(mixed $qb_response, Invoice $invoice): void
    {
        $qb_total_tax = (float) data_get($qb_response, 'TxnTaxDetail.TotalTax', 0);
        $qb_total_amt = (float) data_get($qb_response, 'TotalAmt', 0);
        $qb_subtotal = $qb_total_amt - $qb_total_tax;
        $invoice_calc = $invoice->calc();
        $ninja_total_tax = (float) $invoice->total_taxes;
        $ninja_total_amt = (float) $invoice->amount;
        $ninja_subtotal = (float) $invoice_calc->getSubTotal();
        $tolerance = 0.01;
        $mismatches = [];

        if (abs($qb_total_tax - $ninja_total_tax) > $tolerance) {
            $mismatches[] = [
                'type' => 'tax',
                'qb' => $qb_total_tax,
                'ninja' => $ninja_total_tax,
                'difference' => abs($qb_total_tax - $ninja_total_tax),
            ];
            nlog("QuickBooks: Invoice {$invoice->id} tax amount mismatch - QB: {$qb_total_tax}, Ninja: {$ninja_total_tax}, Difference: " . abs($qb_total_tax - $ninja_total_tax));
        }

        if (abs($qb_total_amt - $ninja_total_amt) > $tolerance) {
            $mismatches[] = [
                'type' => 'amount',
                'qb' => $qb_total_amt,
                'ninja' => $ninja_total_amt,
                'difference' => abs($qb_total_amt - $ninja_total_amt),
            ];
            nlog("QuickBooks: Invoice {$invoice->id} amount mismatch - QB TotalAmt: {$qb_total_amt}, Ninja amount: {$ninja_total_amt}, Difference: " . abs($qb_total_amt - $ninja_total_amt));
        }

        if (abs($qb_subtotal - $ninja_subtotal) > ($tolerance * 2)) {
            $mismatches[] = [
                'type' => 'subtotal',
                'qb' => $qb_subtotal,
                'ninja' => $ninja_subtotal,
                'difference' => abs($qb_subtotal - $ninja_subtotal),
            ];
            nlog("QuickBooks: Invoice {$invoice->id} subtotal mismatch - QB: {$qb_subtotal}, Ninja: {$ninja_subtotal}, Difference: " . abs($qb_subtotal - $ninja_subtotal));
        }

        if (!empty($mismatches)) {
            nlog("QuickBooks: Syncing Invoice {$invoice->id} amounts to match QuickBooks. Mismatches: " . json_encode($mismatches));

            if (abs($qb_total_tax - $ninja_total_tax) > $tolerance) {
                $invoice->total_taxes = round($qb_total_tax, 2);
            }

            if (abs($qb_total_amt - $ninja_total_amt) > $tolerance) {
                $invoice->amount = round($qb_total_amt, 2);
                $invoice = $invoice->calc()->getInvoice();
            }

            nlog("QuickBooks: Invoice {$invoice->id} amounts synced - Tax: {$invoice->total_taxes}, Amount: {$invoice->amount}, Balance: {$invoice->balance}");
        } else {
            nlog("QuickBooks: Invoice {$invoice->id} amounts validated - all match QuickBooks");
        }
    }
}
