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
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Models\Invoice;
use App\Services\Quickbooks\Mapping\InvoiceTaxCodeResolver;
use App\Services\Quickbooks\Mapping\QuickbooksInvoiceMapper;
use App\Services\Quickbooks\Mapping\TaxExportContext;
use App\Services\Quickbooks\Mapping\TxnTaxDetailBuilder;
use App\Services\Quickbooks\QuickbooksService;

/**
 * Facade over QuickBooks invoice mapping. I/O stays here; payload shape lives in Mapping\*.
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param  mixed  $qb_data
     * @return array<string, mixed>|bool
     */
    public function qbToNinja(mixed $qb_data, ?QuickbooksService $qb_service = null): array|bool
    {
        return $this->transform($qb_data, $qb_service);
    }

    /**
     * @return array<string, mixed>
     */
    public function ninjaToQb(Invoice $invoice, QuickbooksService $qb_service): array
    {
        $resolver = new InvoiceTaxCodeResolver();
        $calc = $invoice->calc();
        $discount = (float) $calc->getTotalDiscount();
        $has_customer_memo = $invoice->public_notes || $invoice->terms;

        return (new QuickbooksInvoiceMapper($resolver, new TxnTaxDetailBuilder($resolver)))->map(
            $invoice,
            $this->provisionTaxExportContext($invoice, $qb_service, $resolver, $calc),
            $this->resolveProductQbIds($invoice, $resolver, $qb_service),
            $discount > 0 ? $qb_service->helper->getDiscountAccountId() : null,
            (float) $calc->getTotalTaxes(),
            $discount,
            $calc->getTaxMap() ?? [],
            $has_customer_memo ? $qb_service->helper->cleanHtmlText($invoice->public_notes ?? '') : '',
            $has_customer_memo ? $qb_service->helper->cleanHtmlText($invoice->terms ?? '') : '',
            $invoice->private_notes ? $qb_service->helper->cleanHtmlText($invoice->private_notes) : '',
        );
    }

    /**
     * @param  mixed  $qb_data
     * @return array<string, mixed>|bool
     */
    public function transform(mixed $qb_data, ?QuickbooksService $qb_service = null): array|bool
    {
        $customer_ref = data_get($qb_data, 'CustomerRef', null);

        $client_id = ($qb_service && $customer_ref)
            ? $qb_service->client->findOrCreateClient((string) $customer_ref)
            : $this->getClientId($customer_ref);

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

    /**
     * @return array<int, string>
     */
    private function resolveProductQbIds(Invoice $invoice, InvoiceTaxCodeResolver $resolver, QuickbooksService $qb_service): array
    {
        $invoice_level_taxes = $resolver->extractInvoiceLevelTaxes($invoice);
        $product_qb_ids = [];

        foreach ($invoice->line_items as $index => $line_item) {
            $line_item = $resolver->mergeInvoiceLevelTaxes($line_item, $invoice_level_taxes);

            try {
                $product_qb_id = $qb_service->product->findOrCreateProduct($line_item);

                if (empty($product_qb_id)) {
                    nlog('QuickBooks: ninjaToQb skipped line — findOrCreateProduct returned empty QuickBooks item Id', [
                        'invoice_id' => $invoice->id,
                        'product_key' => $line_item->product_key ?? null,
                    ]);
                    continue;
                }

                $product_qb_ids[$index] = $product_qb_id;
            } catch (QuickbooksMissingTaxCode $e) {
                throw $e;
            } catch (\Throwable $e) {
                nlog('QuickBooks: ninjaToQb skipped line — product find/create or line build failed', [
                    'invoice_id' => $invoice->id,
                    'product_key' => $line_item->product_key ?? null,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $product_qb_ids;
    }

    private function provisionTaxExportContext(
        Invoice $invoice,
        QuickbooksService $qb_service,
        InvoiceTaxCodeResolver $resolver,
        InvoiceSum|InvoiceSumInclusive $calc,
    ): TaxExportContext {
        $context = TaxExportContext::fromQuickbooksSync($qb_service->company->quickbooks->settings);
        $invoice_level_taxes = $resolver->extractInvoiceLevelTaxes($invoice);

        if (!$context->isUs() && !$context->automatic_taxes) {
            $unresolved_tax_components = $resolver->unresolvedTaxCodeComponents(
                $invoice,
                $invoice_level_taxes,
                $context->tax_rate_map,
                $context->composite_tax_code_map
            );

            if (!empty($unresolved_tax_components)) {
                nlog('QB: refreshing TaxCode index before invoice push for unresolved taxes', [
                    'invoice_id' => $invoice->id,
                    'company_id' => $qb_service->company->id,
                    'component_keys' => array_keys($unresolved_tax_components),
                ]);

                try {
                    $qb_service->companySync();
                    $context = TaxExportContext::fromQuickbooksSync($qb_service->company->quickbooks->settings);
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
        }

        if (!$context->isUs() && !$context->automatic_taxes) {
            $unresolved_tax_components = $resolver->unresolvedTaxCodeComponents(
                $invoice,
                $invoice_level_taxes,
                $context->tax_rate_map,
                $context->composite_tax_code_map
            );

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

                    $context = $context->withMaps(
                        $qb_service->company->quickbooks->settings->tax_rate_map ?? [],
                        $qb_service->company->quickbooks->settings->composite_tax_code_map ?? []
                    );
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

                $unresolved_tax_components = $resolver->unresolvedTaxCodeComponents(
                    $invoice,
                    $invoice_level_taxes,
                    $context->tax_rate_map,
                    $context->composite_tax_code_map
                );

                if (!empty($unresolved_tax_components)) {
                    nlog('QB: missing TaxCode for invoice taxes after create attempt; invoice push blocked', [
                        'invoice_id' => $invoice->id,
                        'company_id' => $qb_service->company->id,
                        'component_keys' => array_keys($unresolved_tax_components),
                    ]);

                    throw QuickbooksMissingTaxCode::forComponentGroups($unresolved_tax_components);
                }
            }
        }

        if (!$context->isUs() && $context->exempt_code === 'NON') {
            nlog("QB Warning: exempt TaxCode not resolved for non-US company {$qb_service->company->id} (country={$context->country}), falling back to taxable code '{$context->taxable_code}' — run companySync to fix");
            $context = $context->withNormalizedExemptCode();
        }

        if ($context->includesTxnTaxDetail()) {
            $context = $this->provisionUsTaxRates($context, $qb_service, $resolver, $calc);
        }

        return $context;
    }

    private function provisionUsTaxRates(
        TaxExportContext $context,
        QuickbooksService $qb_service,
        InvoiceTaxCodeResolver $resolver,
        InvoiceSum|InvoiceSumInclusive $calc,
    ): TaxExportContext {
        $tax_rate_map = $context->tax_rate_map;

        foreach ($calc->getTaxMap() ?? [] as $tax) {
            $tax_name = (string) $tax['name'];
            $tax_rate = (float) $tax['tax_rate'];

            if ($resolver->findTaxRateIdByRateAndName($tax_rate_map, $tax_rate, $tax_name)) {
                continue;
            }

            $qb_service->tax_rate->ensureTaxCodeForComponents([
                ['name' => $tax_name, 'rate' => $tax_rate],
            ]);

            $tax_rate_map = $qb_service->company->quickbooks->settings->tax_rate_map;
            $context = $context->withMaps($tax_rate_map, $context->composite_tax_code_map);
        }

        return $context;
    }
}
