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

namespace App\Services\Quickbooks\Mapping;

use App\Models\Invoice;
use Exception;

final class QuickbooksInvoiceMapper
{
    public function __construct(
        private InvoiceTaxCodeResolver $tax_code_resolver,
        private TxnTaxDetailBuilder $txn_tax_detail_builder,
    ) {
    }

    /**
     * @param  array<int, string>  $product_qb_ids
     * @param  iterable<int, array<string, mixed>>  $tax_map
     * @return array<string, mixed>
     */
    public function map(
        Invoice $invoice,
        TaxExportContext $context,
        array $product_qb_ids,
        ?string $discount_account_id,
        float $total_taxes,
        float $discount,
        iterable $tax_map,
        string $public_notes,
        string $terms,
        string $private_notes,
    ): array {
        $invoice_level_taxes = $this->tax_code_resolver->extractInvoiceLevelTaxes($invoice);
        $line_items = [];
        $line_num = 1;

        foreach ($invoice->line_items as $index => $line_item) {
            if (!isset($product_qb_ids[$index])) {
                continue;
            }

            $line_item = $this->tax_code_resolver->mergeInvoiceLevelTaxes($line_item, $invoice_level_taxes);
            $line_items[] = $this->salesLine($line_item, $line_num, $product_qb_ids[$index], $context);
            $line_num++;
        }

        if (empty($line_items)) {
            $error_msg = "QuickBooks: Invoice {$invoice->id} cannot be created - no valid line items could be processed.";
            nlog($error_msg);
            throw new Exception($error_msg);
        }

        $primary_contact = $invoice->client->contacts()->orderBy('is_primary', 'desc')->first();
        $email = $primary_contact->email ?? $invoice->client->contacts()->first()->email ?? '';

        if ($discount > 0) {
            $line_items[] = $this->discountLine($invoice, $line_num, round($discount, 2), $discount_account_id);
        }

        $invoice_data = [
            'Line' => $line_items,
            'CustomerRef' => [
                'value' => $invoice->client->sync->qb_id ?? null,
            ],
            'BillEmail' => [
                'Address' => mb_substr($email, 0, 100),
            ],
            'TxnDate' => $invoice->date,
            'DueDate' => $invoice->due_date,
            'TotalAmt' => $invoice->amount,
            'DocNumber' => mb_substr($invoice->number ?? '', 0, 21),
            'ApplyTaxAfterDiscount' => true,
            'PrintStatus' => 'NeedToPrint',
            'EmailStatus' => 'NotSet',
        ];

        if ($context->usesTaxExcludedCalculation()) {
            $invoice_data['GlobalTaxCalculation'] = 'TaxExcluded';
        }

        if ($ship_addr = $this->formatLocationShipAddress($invoice)) {
            $invoice_data['ShipAddr'] = $ship_addr;
        }

        if ($context->includesTxnTaxDetail()) {
            $tax_detail = $this->txn_tax_detail_builder->build($tax_map, $total_taxes, $context->tax_rate_map);
            if ($tax_detail) {
                $invoice_data['TxnTaxDetail'] = $tax_detail;
            }
        }

        if ($public_notes !== '' || $terms !== '') {
            $memo_value = trim($public_notes . ($public_notes && $terms ? "\n\n" : '') . $terms);

            if ($memo_value) {
                $invoice_data['CustomerMemo'] = [
                    'value' => mb_substr($memo_value, 0, 1000),
                ];
            }
        }

        if ($private_notes !== '') {
            $invoice_data['PrivateNote'] = mb_substr($private_notes, 0, 4000);
        }

        if ($invoice->po_number) {
            $invoice_data['PONumber'] = mb_substr($invoice->po_number, 0, 25);
        }

        if ($invoice->partial && $invoice->partial > 0) {
            $invoice_data['Deposit'] = $invoice->partial;
        }

        if (isset($invoice->sync->qb_id) && !empty($invoice->sync->qb_id)) {
            $invoice_data['Id'] = $invoice->sync->qb_id;
        }

        return $invoice_data;
    }

    /**
     * @return array<string, string>|null
     */
    public function formatLocationShipAddress(Invoice $invoice): ?array
    {
        if (!$invoice->location_id) {
            return null;
        }

        $invoice->loadMissing('location.country');

        $location = $invoice->location;

        if (!$location) {
            return null;
        }

        return [
            'Line1' => mb_substr($location->address1 ?? '', 0, 41),
            'Line2' => mb_substr($location->address2 ?? '', 0, 41),
            'City' => mb_substr($location->city ?? '', 0, 31),
            'CountrySubDivisionCode' => mb_substr($location->state ?? '', 0, 21),
            'PostalCode' => mb_substr($location->postal_code ?? '', 0, 13),
            'Country' => $location->country->iso_3166_3 ?? '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesLine(object $line_item, int $line_num, string $product_qb_id, TaxExportContext $context): array
    {
        return [
            'LineNum' => $line_num,
            'DetailType' => 'SalesItemLineDetail',
            'SalesItemLineDetail' => [
                'ItemRef' => [
                    'value' => $product_qb_id,
                ],
                'Qty' => $line_item->quantity ?? 1,
                'UnitPrice' => $line_item->cost ?? 0,
                'TaxCodeRef' => [
                    'value' => $this->tax_code_resolver->resolveForLine($line_item, $context),
                ],
            ],
            'Description' => mb_substr($line_item->notes ?? '', 0, 4000),
            'Amount' => $line_item->line_total ?? ($line_item->cost * ($line_item->quantity ?? 1)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function discountLine(Invoice $invoice, int $line_num, float $discount_amount, ?string $discount_account_id): array
    {
        $discount_line = [
            'LineNum' => $line_num,
            'DetailType' => 'DiscountLineDetail',
            'Amount' => $discount_amount,
            'DiscountLineDetail' => [
                'PercentBased' => !$invoice->is_amount_discount,
            ],
        ];

        if ($discount_account_id) {
            $discount_line['DiscountLineDetail']['DiscountAccountRef'] = [
                'value' => $discount_account_id,
            ];
        }

        if (!$invoice->is_amount_discount && $invoice->discount > 0) {
            $discount_line['DiscountLineDetail']['DiscountPercent'] = round($invoice->discount, 2);
        } else {
            $discount_line['DiscountLineDetail']['DiscountPercent'] = 0.0;
        }

        return $discount_line;
    }
}
