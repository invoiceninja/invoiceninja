<?php

/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use App\Exceptions\QuickbooksMissingTaxCode;
use App\Models\Invoice;
use App\Services\Quickbooks\TaxCodeComponentKey;

/**
 * Class InvoiceTransformer.
 *
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * qbToNinja
     *
     * Transforms a QB invoice to a Invoice Ninja Invoice
     *
     * @param  mixed $qb_data
     * @param  \App\Services\Quickbooks\QuickbooksService|null $qb_service
     * @return array
     */
    public function qbToNinja(mixed $qb_data, ?\App\Services\Quickbooks\QuickbooksService $qb_service = null)
    {
        return $this->transform($qb_data, $qb_service);
    }

    /**
     * ninjaToQb
     *
     * Transforms a Invoice Ninja Invoice to a QB invoice
     *
     * @param  \App\Models\Invoice $invoice
     * @param  \App\Services\Quickbooks\QuickbooksService $qb_service
     * @return array
     */
    public function ninjaToQb(Invoice $invoice, \App\Services\Quickbooks\QuickbooksService $qb_service): array
    {
        // Get client's QuickBooks ID (business logic handled by caller - QbInvoice)
        $client_qb_id = $invoice->client->sync->qb_id ?? null;

        // Build line items
        $line_items = [];
        $line_num = 1;

        $ast = $qb_service->company->quickbooks->settings->automatic_taxes;
        $taxable_code = $qb_service->company->quickbooks->settings->default_taxable_code ?? 'TAX';
        $exempt_code = $qb_service->company->quickbooks->settings->default_exempt_code ?? 'NON';
        $tax_rate_map = $qb_service->company->quickbooks->settings->tax_rate_map ?? [];
        $composite_tax_code_map = $qb_service->company->quickbooks->settings->composite_tax_code_map ?? [];

        // Determine region from stored QB company country (set by companySync)
        $qb_country = $qb_service->company->quickbooks->settings->country ?? 'US';
        $is_us = ($qb_country === 'US');

        // US companies MUST use "TAX"/"NON" as TaxCodeRef — never numeric IDs.
        // Force correct values regardless of what companySync stored (handles existing data).
        if ($is_us) {
            $taxable_code = 'TAX';
            $exempt_code = 'NON';
        }

        $invoice_level_taxes = $this->extractInvoiceLevelTaxes($invoice);
        $unresolved_tax_components = (!$is_us && !$ast)
            ? $this->unresolvedTaxCodeComponents($invoice, $invoice_level_taxes, $tax_rate_map, $composite_tax_code_map)
            : [];

        if (!empty($unresolved_tax_components)) {
            nlog('QB: refreshing TaxCode index before invoice push for unresolved taxes', [
                'invoice_id' => $invoice->id,
                'company_id' => $qb_service->company->id,
                'component_keys' => array_keys($unresolved_tax_components),
            ]);

            try {
                $qb_service->companySync();

                $ast = $qb_service->company->quickbooks->settings->automatic_taxes;
                $taxable_code = $qb_service->company->quickbooks->settings->default_taxable_code ?? 'TAX';
                $exempt_code = $qb_service->company->quickbooks->settings->default_exempt_code ?? 'NON';
                $tax_rate_map = $qb_service->company->quickbooks->settings->tax_rate_map ?? [];
                $composite_tax_code_map = $qb_service->company->quickbooks->settings->composite_tax_code_map ?? [];
                $qb_country = $qb_service->company->quickbooks->settings->country ?? 'US';
                $is_us = ($qb_country === 'US');
            } catch (\Throwable $e) {
                nlog('QB: failed to refresh TaxCode index before invoice push for unresolved taxes; continuing with cached tax map', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $qb_service->company->id,
                    'component_keys' => array_keys($unresolved_tax_components),
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($is_us) {
            $taxable_code = 'TAX';
            $exempt_code = 'NON';
        }

        if (!$is_us && !$ast) {
            $unresolved_tax_components = $this->unresolvedTaxCodeComponents($invoice, $invoice_level_taxes, $tax_rate_map, $composite_tax_code_map);

            if (!empty($unresolved_tax_components)) {
                nlog('QB: creating missing TaxServices before invoice push', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $qb_service->company->id,
                    'component_keys' => array_keys($unresolved_tax_components),
                ]);

                try {
                    foreach ($unresolved_tax_components as $components) {
                        $qb_service->tax_rate->ensureTaxCodeForComponents($components);
                    }

                    $tax_rate_map = $qb_service->company->quickbooks->settings->tax_rate_map ?? [];
                    $composite_tax_code_map = $qb_service->company->quickbooks->settings->composite_tax_code_map ?? [];
                } catch (\Throwable $e) {
                    nlog('QB: failed to create missing TaxServices before invoice push', [
                        'invoice_id' => $invoice->id,
                        'company_id' => $qb_service->company->id,
                        'component_keys' => array_keys($unresolved_tax_components),
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);

                    throw QuickbooksMissingTaxCode::forComponentGroups($unresolved_tax_components, $e);
                }

            }

            // re-check for unresolved tax components after creating the tax codes
            $unresolved_tax_components = $this->unresolvedTaxCodeComponents($invoice, $invoice_level_taxes, $tax_rate_map, $composite_tax_code_map);

            if (!empty($unresolved_tax_components)) {
                nlog('QB: missing TaxCode for invoice taxes after create attempt; invoice push blocked', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $qb_service->company->id,
                    'component_keys' => array_keys($unresolved_tax_components),
                ]);

                throw QuickbooksMissingTaxCode::forComponentGroups($unresolved_tax_components);
            }

        }

        // Non-US regions (CA/AU/UK) require TaxCodeRef on EVERY line item using numeric tax code IDs.
        // US companies MUST use only "TAX" or "NON" as TaxCodeRef values.
        if (!$is_us && $exempt_code === 'NON') {
            nlog("QB Warning: exempt TaxCode not resolved for non-US company {$qb_service->company->id} (country={$qb_country}), falling back to taxable code '{$taxable_code}' — run companySync to fix");
            $exempt_code = $taxable_code;
        }

        foreach ($invoice->line_items as $line_item) {
            $line_item = $this->mergeInvoiceLevelTaxes($line_item, $invoice_level_taxes);

            try {
                // Get product's QuickBooks ID (business logic handled by QbProduct)
                $product_qb_id = $qb_service->product->findOrCreateProduct($line_item);

                // Skip line items where product creation failed (null or empty)
                if (empty($product_qb_id)) {
                    nlog('QuickBooks: ninjaToQb skipped line — findOrCreateProduct returned empty QuickBooks item Id', [
                        'invoice_id' => $invoice->id,
                        'product_key' => $line_item->product_key ?? null,
                    ]);
                    continue;
                }

                // Determine TaxCodeRef from line-item taxes only (never invoice-level taxes for QB sync)
                if (isset($line_item->tax_id) && in_array($line_item->tax_id, ['5', '8'])) {
                    $tax_code_id = $exempt_code;
                } elseif ($ast) {
                    $tax_code_id = $taxable_code;
                } elseif ($is_us) {
                    // US companies: TaxCodeRef MUST be "TAX" or "NON" — never numeric IDs
                    $tax_code_id = $this->resolveLineTaxCodeUS($line_item, $taxable_code, $exempt_code);
                } else {
                    // Non-US companies (CA/AU/UK): resolve to numeric TaxCode ID from tax_rate_map
                    $tax_code_id = $this->resolveLineTaxCode($line_item, $tax_rate_map, $composite_tax_code_map, $taxable_code, $exempt_code);
                }

                $line_payload = [
                    'LineNum' => $line_num,
                    'DetailType' => 'SalesItemLineDetail',
                    'SalesItemLineDetail' => [
                        'ItemRef' => [
                            'value' => $product_qb_id,
                        ],
                        'Qty' => $line_item->quantity ?? 1,
                        'UnitPrice' => $line_item->cost ?? 0,
                        'TaxCodeRef' => [
                            'value' => $tax_code_id,
                        ],
                    ],
                    // QuickBooks Description max length is 4000 characters
                    'Description' => mb_substr($line_item->notes ?? '', 0, 4000),
                    'Amount' => $line_item->line_total ?? ($line_item->cost * ($line_item->quantity ?? 1)),
                ];

                $line_items[] = $line_payload;

                $line_num++;
            } catch (QuickbooksMissingTaxCode $e) {
                throw $e;
            } catch (\Throwable $e) {
                nlog('QuickBooks: ninjaToQb skipped line — product find/create or line build failed', [
                    'invoice_id' => $invoice->id,
                    'product_key' => $line_item->product_key ?? null,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }
        }

        // QuickBooks requires at least one line item
        if (empty($line_items)) {
            $error_msg = "QuickBooks: Invoice {$invoice->id} cannot be created - no valid line items could be processed.";
            nlog($error_msg);
            throw new \Exception($error_msg);
        }

        // Get primary contact email
        $primary_contact = $invoice->client->contacts()->orderBy('is_primary', 'desc')->first();
        $email = $primary_contact?->email ?? $invoice->client->contacts()->first()?->email ?? '';

        // Calculate invoice to get accurate tax information
        $invoice_calc = $invoice->calc();
        $total_taxes = $invoice_calc->getTotalTaxes();
        $subtotal = $invoice_calc->getSubTotal();
        $discount = $invoice_calc->getTotalDiscount();
        $surcharges = $invoice_calc->getTotalSurcharges();

        // Calculate taxable amount (subtotal - discount + surcharges, before taxes)
        $taxable_amount = $subtotal - $discount + $surcharges;

        // Add discount as a line item if discount exists
        if ($discount > 0) {
            // QuickBooks expects positive Amount for DiscountLineDetail (it handles the negative internally)
            $discount_amount = (float) round($discount, 2);

            // Get discount account ID using helper
            $discount_account_id = $qb_service->helper->getDiscountAccountId();

            $discount_line = [
                'LineNum' => $line_num,
                'DetailType' => 'DiscountLineDetail',
                'Amount' => $discount_amount, // Positive amount - QuickBooks handles the negative
                'DiscountLineDetail' => [
                    'PercentBased' => !$invoice->is_amount_discount, // true for percentage, false for amount
                ],
            ];

            // Add DiscountAccountRef if available (may be required by QuickBooks)
            if ($discount_account_id) {
                $discount_line['DiscountLineDetail']['DiscountAccountRef'] = [
                    'value' => $discount_account_id,
                ];
            }

            if (!$invoice->is_amount_discount && $invoice->discount > 0) {
                // For percentage-based discounts, set DiscountPercent
                $discount_line['DiscountLineDetail']['DiscountPercent'] = round($invoice->discount, 2);
            } else {
                // For amount-based discounts, set DiscountPercent to 0.0 (as suggested)
                $discount_line['DiscountLineDetail']['DiscountPercent'] = 0.0;
            }

            $line_items[] = $discount_line;
            $line_num++;
        }

        // Build invoice data
        $invoice_data = [
            'Line' => $line_items,
            'CustomerRef' => [
                'value' => $client_qb_id,
            ],
            'BillEmail' => [
                // QuickBooks Email Address max length is 100 characters
                'Address' => mb_substr($email, 0, 100),
            ],
            'TxnDate' => $invoice->date,
            'DueDate' => $invoice->due_date,
            'TotalAmt' => $invoice->amount,
            // QuickBooks DocNumber max length is 21 characters
            'DocNumber' => mb_substr($invoice->number ?? '', 0, 21),
            'ApplyTaxAfterDiscount' => true,
            'PrintStatus' => 'NeedToPrint',
            'EmailStatus' => 'NotSet',
            'GlobalTaxCalculation' => ($ast || !$is_us) ? 'TaxExcluded' : 'NotApplicable',
        ];

        // Only send TxnTaxDetail for US companies without AST.
        // Non-US companies use resolved TaxCodeRef per line item — QB calculates taxes from those.
        if (!$ast && $is_us) {
            $tax_detail = $this->buildTxnTaxDetail($invoice, $total_taxes, $taxable_amount, $qb_service);
            if ($tax_detail) {
                $invoice_data['TxnTaxDetail'] = $tax_detail;
            }
        }

        // Add optional fields
        if ($invoice->public_notes || $invoice->terms) {
            $public_notes = $invoice->public_notes ?? '';
            $terms = $invoice->terms ?? '';

            // Clean HTML: replace <br> tags with newlines and strip all HTML tags
            $public_notes = $qb_service->helper->cleanHtmlText($public_notes);
            $terms = $qb_service->helper->cleanHtmlText($terms);

            // Combine public notes and terms
            $memo_value = trim($public_notes . ($public_notes && $terms ? "\n\n" : '') . $terms);

            if ($memo_value) {
                // QuickBooks CustomerMemo max length is 1000 characters
                $invoice_data['CustomerMemo'] = [
                    'value' => mb_substr($memo_value, 0, 1000),
                ];
            }
        }

        if ($invoice->private_notes) {
            // QuickBooks PrivateNote max length is 4000 characters
            $invoice_data['PrivateNote'] = mb_substr($qb_service->helper->cleanHtmlText($invoice->private_notes), 0, 4000);
        }

        if ($invoice->po_number) {
            // QuickBooks PONumber max length is 25 characters
            $invoice_data['PONumber'] = mb_substr($invoice->po_number, 0, 25);
        }

        // QuickBooks uses 'Deposit' field for partial payments/deposits
        if ($invoice->partial && $invoice->partial > 0) {
            $invoice_data['Deposit'] = $invoice->partial;
        }

        // If invoice already has a QB ID, include it for updates
        if (isset($invoice->sync->qb_id) && !empty($invoice->sync->qb_id)) {
            $invoice_data['Id'] = $invoice->sync->qb_id;
        }

        return $invoice_data;
    }


    /**
     * Build a map of non-empty invoice-level tax slots.
     *
     * QuickBooks only resolves TaxCodeRef from per-line tax fields; invoices
     * carrying tax at the document level need those rates merged into each
     * line for the resolver to pick the taxable code. Returning an empty
     * array short-circuits the merge in {@see mergeInvoiceLevelTaxes()}.
     *
     * @return array<string, string|float>
     */
    private function extractInvoiceLevelTaxes(Invoice $invoice): array
    {
        $taxes = [];

        foreach ([1, 2, 3] as $i) {
            $name = $invoice->{"tax_name{$i}"};

            if (is_string($name) && strlen($name) > 1) {
                $taxes["tax_name{$i}"] = $name;
                $taxes["tax_rate{$i}"] = $invoice->{"tax_rate{$i}"};
            }
        }

        return $taxes;
    }

    /**
     * Return a line item with invoice-level taxes copied in, without
     * mutating the original. An invoice carries taxes at either the
     * document level or the line level — never both — so the merge
     * is unconditional.
     */
    private function mergeInvoiceLevelTaxes(object $line_item, array $invoice_level_taxes): object
    {
        if (empty($invoice_level_taxes)) {
            return $line_item;
        }

        $merged = clone $line_item;

        foreach ($invoice_level_taxes as $key => $value) {
            $merged->{$key} = $value;
        }

        return $merged;
    }

    /**
     * @return array<string, array<int, array{name: string, rate: float}>>
     */
    private function unresolvedTaxCodeComponents(Invoice $invoice, array $invoice_level_taxes, array $tax_rate_map, array $composite_tax_code_map): array
    {
        $missing_components = [];

        foreach ($invoice->line_items as $line_item) {
            if (isset($line_item->tax_id) && in_array((string) $line_item->tax_id, ['5', '8'], true)) {
                continue;
            }

            $line_item = $this->mergeInvoiceLevelTaxes($line_item, $invoice_level_taxes);
            $components = $this->taxComponentsFromLineItem($line_item);

            if (empty($components)) {
                continue;
            }

            $component_key = TaxCodeComponentKey::fromComponents($components);

            if ($component_key === '') {
                continue;
            }

            if (count($components) === 1 && $this->findTaxCodeIdByRate($tax_rate_map, $components[0]['rate'], $components[0]['name']) === null) {
                $missing_components[$component_key] = $components;
                continue;
            }

            if (count($components) > 1 && $this->findCompositeTaxCodeId($components, $composite_tax_code_map) === null) {
                $missing_components[$component_key] = $components;
            }
        }

        return $missing_components;
    }

    /**
     * Resolve the TaxCodeRef for a single line item by matching its tax name/rate
     * to the tax_rate_map (which includes tax_code_id from SalesTaxRateList).
     *
     * @param  object $line_item The invoice line item
     * @param  array $tax_rate_map The tax rate map with tax_code_id entries
     * @param  string $taxable_code Default taxable TaxCode ID
     * @param  string $exempt_code Default exempt TaxCode ID
     * @return string The resolved TaxCode ID
     */
    private function resolveLineTaxCode(object $line_item, array $tax_rate_map, array $composite_tax_code_map, string $taxable_code, string $exempt_code): string
    {
        $components = $this->taxComponentsFromLineItem($line_item);

        if (empty($components)) {
            return $exempt_code;
        }

        if (count($components) === 1) {
            $tax_code_id = $this->findTaxCodeIdByRate($tax_rate_map, $components[0]['rate'], $components[0]['name']);

            if ($tax_code_id) {
                return $tax_code_id;
            }

            nlog('QB: no TaxCode for invoice tax; invoice push blocked', [
                'components' => $components,
            ]);

            throw QuickbooksMissingTaxCode::forComponents($components);
        }

        $tax_code_id = $this->findCompositeTaxCodeId($components, $composite_tax_code_map);

        if ($tax_code_id) {
            return $tax_code_id;
        }

        nlog('QB: no composite TaxCode for combined invoice taxes; invoice push blocked', [
            'components' => $components,
        ]);

        throw QuickbooksMissingTaxCode::forComponents($components);
    }

    /**
     * @return array<int, array{name: string, rate: float}>
     */
    private function taxComponentsFromLineItem(object $line_item): array
    {
        $components = [];

        foreach ([1, 2, 3] as $index) {
            $rate = (float) ($line_item->{"tax_rate{$index}"} ?? 0);

            if ($rate <= 0) {
                continue;
            }

            $components[] = [
                'name' => trim((string) ($line_item->{"tax_name{$index}"} ?? '')),
                'rate' => $rate,
            ];
        }

        return $components;
    }

    /**
     * @param  array<int, array{name: string, rate: float}>  $components
     */
    private function findCompositeTaxCodeId(array $components, array $composite_tax_code_map): ?string
    {
        $component_key = TaxCodeComponentKey::fromComponents($components);
        $candidates = $composite_tax_code_map[$component_key] ?? [];

        if (is_string($candidates)) {
            return $candidates;
        }

        if (!is_array($candidates) || empty($candidates)) {
            return null;
        }

        if (isset($candidates['tax_code_id'])) {
            $candidates = [$candidates];
        }

        $candidate_ids = [];

        foreach ($candidates as $candidate) {
            $candidate_id = is_array($candidate) ? (string) ($candidate['tax_code_id'] ?? '') : (string) $candidate;

            if ($candidate_id !== '') {
                $candidate_ids[] = $candidate_id;
            }
        }

        $candidate_ids = array_values(array_unique($candidate_ids));

        if (count($candidate_ids) === 1) {
            return $candidate_ids[0];
        }

        if (count($candidate_ids) > 1) {
            nlog('QB: ambiguous composite TaxCode for combined invoice taxes; invoice push blocked', [
                'component_key' => $component_key,
                'candidates' => $candidates,
            ]);
        }

        return null;
    }

    /**
     * Resolve the TaxCodeRef for a US company line item.
     *
     * US QuickBooks companies ONLY accept "TAX" or "NON" as line-level TaxCodeRef values.
     * Checks whether the line item has any non-zero tax rates and returns the appropriate code.
     */
    private function resolveLineTaxCodeUS(object $line_item, string $taxable_code, string $exempt_code): string
    {
        $has_line_tax = (
            (isset($line_item->tax_rate1) && $line_item->tax_rate1 > 0)
            || (isset($line_item->tax_rate2) && $line_item->tax_rate2 > 0)
            || (isset($line_item->tax_rate3) && $line_item->tax_rate3 > 0)
        );

        return $has_line_tax ? $taxable_code : $exempt_code;
    }

    /**
     * Build TxnTaxDetail for invoice-level tax calculation.
     * This handles total taxes applied to the invoice.
     *
     * @param \App\Models\Invoice $invoice
     * @param float $total_taxes The total tax amount
     * @param float $taxable_amount The taxable amount (subtotal - discount + surcharges)
     * @param \App\Services\Quickbooks\QuickbooksService $qb_service
     * @return array|null TxnTaxDetail array or null if no taxes
     */
    private function buildTxnTaxDetail(\App\Models\Invoice $invoice, float $total_taxes, float $taxable_amount, \App\Services\Quickbooks\QuickbooksService $qb_service): ?array
    {
        // Build TxnTaxDetail from LINE-ITEM taxes only (getTaxMap).
        // Invoice-level taxes (getTotalTaxMap) are never used for QB sync.
        $tax_lines = [];
        $calculated_total_tax = 0;

        $tax_rate_map = $qb_service->company->quickbooks->settings->tax_rate_map ?? [];

        foreach ($invoice->calc()->getTaxMap() ?? [] as $tax) {
            $tax_components = $qb_service->helper->splitTaxName($tax['name']);
            $tax_rate_id = $this->findTaxRateIdByRateAndName($tax_rate_map, floatval($tax_components['percentage']), $tax_components['name']);

            if (!$tax_rate_id) {
                continue;
            }

            $tax_lines[] = [
                'Amount' => round($tax['total'], 2),
                'DetailType' => 'TaxLineDetail',
                'TaxLineDetail' => [
                    'TaxRateRef' => [
                        'value' => $tax_rate_id,
                    ],
                    'PercentBased' => false,
                    'NetAmountTaxable' => round($tax['base_amount'], 2),
                    'TaxInclusiveAmount' => 0.00,
                ],
            ];

            $calculated_total_tax += round($tax['total'], 2);
        }

        // If no tax lines, return null
        if (empty($tax_lines)) {
            return null;
        }

        // Use the actual total_taxes from invoice if available, otherwise use calculated
        $final_total_tax = $total_taxes > 0 ? round($total_taxes, 2) : round($calculated_total_tax, 2);

        return [
            'TotalTax' => $final_total_tax,
            'TaxLine' => $tax_lines,
        ];
    }


    /**
     * Find a TaxCode ID from the tax rate map by matching rate and name.
     * Uses fuzzy name matching (stripos) with a rate-only fallback.
     */
    private function findTaxCodeIdByRate(array $tax_rate_map, float $rate, string $name): ?string
    {
        $rate_only_match = null;

        foreach ($tax_rate_map as $entry) {
            if (empty($entry['tax_code_id']) || TaxCodeComponentKey::formatRate($entry['rate'] ?? 0) !== TaxCodeComponentKey::formatRate($rate)) {
                continue;
            }

            $entry_name = (string) ($entry['name'] ?? '');

            if ($name === '' || $entry_name === '' || stripos($name, $entry_name) !== false || stripos($entry_name, $name) !== false) {
                return $entry['tax_code_id'];
            }

            $rate_only_match ??= $entry['tax_code_id'];
        }

        return $rate_only_match;
    }

    /**
     * Find a TaxRate ID from the tax rate map by exact rate and name match.
     */
    private function findTaxRateIdByRateAndName(array $tax_rate_map, float $rate, string $name): ?string
    {
        foreach ($tax_rate_map as $entry) {
            if (floatval($entry['rate']) == $rate && $entry['name'] == $name) {
                return $entry['id'];
            }
        }

        return null;
    }

    /**
     * transform
     *
     * @param  mixed $qb_data
     * @param  \App\Services\Quickbooks\QuickbooksService|null $qb_service
     * @return array|bool
     */
    public function transform(mixed $qb_data, ?\App\Services\Quickbooks\QuickbooksService $qb_service = null): array|bool
    {
        $customer_ref = data_get($qb_data, 'CustomerRef', null);

        // Use find-or-create when the QB service is available (fetches & creates the client from QB if needed)
        $client_id = ($qb_service && $customer_ref)
            ? $qb_service->client->findOrCreateClient((string) $customer_ref)
            : $this->getClientId($customer_ref);

        // Use helper for business logic if available, otherwise return basic transformation
        $tax_array = $qb_service ? $qb_service->helper->calculateTotalTax($qb_data) : [0, ''];
        $custom_surcharge1 = $qb_service ? $qb_service->helper->checkIfDiscountAfterTax($qb_data) : 0;

        if (!$client_id) {
            nlog("QuickBooks: Skipping invoice " . data_get($qb_data, 'Id', '?') . " — unable to resolve client for CustomerRef {$customer_ref}");
            return false;
        }

        return [
            'id' => data_get($qb_data, 'Id', false),
            'client_id' => $client_id,
            'number' => data_get($qb_data, 'DocNumber', false),
            'date' => data_get($qb_data, 'TxnDate', now()->format('Y-m-d')),
            'private_notes' => data_get($qb_data, 'PrivateNote', ''),
            'public_notes' => data_get($qb_data, 'CustomerMemo', false),
            'due_date' => data_get($qb_data, 'DueDate', null),
            'po_number' => data_get($qb_data, 'PONumber', ""),
            'partial' => (float) data_get($qb_data, 'Deposit', 0),
            'line_items' => $qb_service ? $qb_service->helper->getLineItems($qb_data, $tax_array) : [],
            'payment_ids' => $qb_service ? $qb_service->helper->getPayments($qb_data) : [],
            'status_id' => Invoice::STATUS_SENT,
            'custom_surcharge1' => $custom_surcharge1,
            'balance' => data_get($qb_data, 'Balance', 0),

        ];
    }

}
