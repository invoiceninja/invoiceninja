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

namespace App\Services\Quickbooks\Models;

use Carbon\Carbon;
use App\Models\Invoice;
use App\DataMapper\InvoiceSync;
use App\Factory\InvoiceFactory;
use App\Interfaces\SyncInterface;
use App\Repositories\InvoiceRepository;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;
use App\Utils\BcMath;

class QbInvoice implements SyncInterface
{
    protected InvoiceTransformer $invoice_transformer;

    protected InvoiceRepository $invoice_repository;

    public function __construct(public QuickbooksService $service)
    {
        $this->invoice_transformer = new InvoiceTransformer($this->service->company);
        $this->invoice_repository = new InvoiceRepository();
    }

    /**
     * find
     *
     * Finds an invoice in QuickBooks by their ID.
     *
     * @param  string $id
     * @return mixed
     */
    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Invoice', $id);
    }

    /**
     * syncToNinja
     *
     * Syncs invoices from QuickBooks to Ninja.
     *
     * @param  array $records
     * @return void
     */
    public function syncToNinja(array $records): void
    {

        foreach ($records as $record) {

            $this->syncNinjaInvoice($record);

        }

    }

    public function importToNinja(array $records): void
    {

        foreach ($records as $record) {

            $ninja_invoice_data = $this->invoice_transformer->qbToNinja($record, $this->service);

            $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];

            $client_id = $ninja_invoice_data['client_id'] ?? null;

            if (is_null($client_id)) {
                continue;
            }

            unset($ninja_invoice_data['payment_ids']);

            if ($invoice = $this->findInvoice($ninja_invoice_data['id'], $ninja_invoice_data['client_id'])) {

                if ($invoice->id) {
                    $this->qbInvoiceUpdate($ninja_invoice_data, $invoice);
                }

                // QB allows duplicate invoice numbers, Ninja does not.
                // Suffix with QB ID to guarantee uniqueness for duplicates.
                if (Invoice::withTrashed()
                    ->where('company_id', $this->service->company->id)
                    ->where('number', $ninja_invoice_data['number'])
                    ->exists()) {
                    $ninja_invoice_data['number'] = $ninja_invoice_data['number'] . '_' . $ninja_invoice_data['id'];
                }

                $invoice->fill($ninja_invoice_data);
                $invoice->saveQuietly();


                // During QB import, use saveQuietly() to prevent circular sync back to QuickBooks
                $invoice = $invoice->calc()->getInvoice()->service()->markSent()->applyNumber()->createInvitations()->save();

                if ($record instanceof \QuickBooksOnline\API\Data\IPPSalesReceipt) {
                    // During QB import, use saveQuietly() to prevent circular sync back to QuickBooks
                    $invoice->service()->markPaid()->save();
                }

            }

            $ninja_invoice_data = false;


        }

    }

    /**
     * syncToForeign
     *
     * Syncs invoices from Ninja to QuickBooks.
     *
     * @param  array $records
     * @return void
     */
    public function syncToForeign(array $records): void
    {
        foreach ($records as $invoice) {
            if (!$invoice instanceof Invoice) {
                continue;
            }

            try {
                // Transform invoice to QuickBooks format
                $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->service);

                // If updating, fetch SyncToken using existing find() method
                if (isset($invoice->sync->qb_id) && !empty($invoice->sync->qb_id)) {
                    $existing_qb_invoice = $this->find($invoice->sync->qb_id);
                    if ($existing_qb_invoice) {
                        $qb_invoice_data['SyncToken'] = $existing_qb_invoice->SyncToken ?? '0';
                    }
                }

                // Create or update invoice in QuickBooks
                $qb_invoice = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);

                $result = false;

                nlog("QuickBooks: Pushing invoice {$invoice->id} payload", ['data' => $qb_invoice_data]);

                if (isset($invoice->sync->qb_id) && !empty($invoice->sync->qb_id)) {
                    $result = $this->service->sdk->Update($qb_invoice);
                    nlog("QuickBooks: Updated invoice {$invoice->id} (QB ID: {$invoice->sync->qb_id})", [
                        'result_id' => data_get($result, 'Id'),
                    ]);
                } else {
                    $result = $this->service->sdk->Add($qb_invoice);

                    $sync = new InvoiceSync();
                    $sync->qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value');
                    $invoice->sync = $sync;
                    $invoice->saveQuietly();

                    nlog("QuickBooks: Created invoice {$invoice->id} (QB ID: {$sync->qb_id})");
                }

                // Process QuickBooks AST response: extract tax details, create missing tax rates, and sync totals
                // Only process if we have a valid result with an ID and automatic taxes are enabled
                $qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value');
                if ($qb_id && ($this->service->company->quickbooks->settings->automatic_taxes ?? false)) {
                    $this->processQuickbooksTaxResponse($result, $invoice);
                }

            } catch (\Exception $e) {
                nlog("QuickBooks: Error pushing invoice {$invoice->id} to QuickBooks: {$e->getMessage()}", [
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        }
    }

    /**
     * Process QuickBooks AST response to extract tax details, create missing tax rates, and sync invoice totals.
     *
     * When using QuickBooks Automated Sales Tax (AST), taxes are calculated by QuickBooks and returned in the response.
     * We need to:
     * 1. Extract TxnTaxDetail from the response
     * 2. Determine if taxes are line-item level or invoice-level
     * 3. Create/update TaxRate records based on TaxRateRef IDs
     * 4. Assign taxes to line items or invoice level accordingly
     * 5. Aggregate taxes if more than 3 exist
     * 6. Recalculate and verify totals match QuickBooks
     *
     * @param mixed $qb_response The QuickBooks invoice response object
     * @param Invoice $invoice The Invoice Ninja invoice to update
     * @return void
     */
    private function processQuickbooksTaxResponse(mixed $qb_response, Invoice $invoice): void
    {
        try {
            // Check if using automated taxes
            $use_ast = $this->service->company->quickbooks->settings->automatic_taxes ?? false;

            if (!$use_ast) {
                // Manual taxes - taxes should already be set from invoice
                return;
            }

            // Extract TxnTaxDetail from response
            $txn_tax_detail = data_get($qb_response, 'TxnTaxDetail');

            if (!$txn_tax_detail) {
                nlog("QuickBooks: No TxnTaxDetail found in response for invoice {$invoice->id}");
                return;
            }

            $total_tax = (float) data_get($txn_tax_detail, 'TotalTax', 0);
            $tax_lines = data_get($txn_tax_detail, 'TaxLine', []);

            // QB can return a single object or an array; normalize to array
            if (!empty($tax_lines)) {
                if (!is_array($tax_lines)) {
                    $tax_lines = [$tax_lines];
                } elseif (!isset($tax_lines[0])) {
                    $tax_lines = [$tax_lines];
                }
            }

            if (empty($tax_lines) || $total_tax <= 0) {
                // No taxes applied - clear all tax fields
                $this->clearAllTaxes($invoice);
                return;
            }

            // Get QuickBooks line items - ALWAYS use line-item level tax processing
            $qb_line_items = data_get($qb_response, 'Line', []);
            if (!empty($qb_line_items)) {
                if (!is_array($qb_line_items)) {
                    $qb_line_items = [$qb_line_items];
                } elseif (!isset($qb_line_items[0])) {
                    $qb_line_items = [$qb_line_items];
                }
            }

            // ALWAYS process taxes at line-item level to correctly handle tax-exempt products
            // Invoice-level taxes are NEVER set - only line-item taxes are used
            if (!empty($qb_line_items)) {
                $this->processLineItemTaxes($qb_line_items, $invoice, $tax_lines);
            } else {
                // Fallback: if no line items, clear invoice taxes (shouldn't happen in practice)
                $invoice->tax_name1 = '';
                $invoice->tax_rate1 = 0;
                $invoice->tax_name2 = '';
                $invoice->tax_rate2 = 0;
                $invoice->tax_name3 = '';
                $invoice->tax_rate3 = 0;
            }

            // Recalculate invoice to ensure totals match QuickBooks
            $invoice->saveQuietly();
            $invoice = $invoice->calc()->getInvoice();

            // Validate and sync amounts with QuickBooks
            $this->validateAndSyncAmounts($qb_response, $invoice);

            $invoice->saveQuietly();

        } catch (\Exception $e) {
            nlog("QuickBooks: Error processing tax response for invoice {$invoice->id}: {$e->getMessage()}");
        }
    }

    /**
     * Process line-item level taxes.
     *
     * In US tax scenarios, line items can have multiple taxes (state, city, county, district).
     * We need to assign all applicable taxes to each taxable line item.
     *
     * @param array $qb_line_items QuickBooks line items
     * @param Invoice $invoice Invoice Ninja invoice
     * @param array $tax_lines Tax lines from TxnTaxDetail
     * @return void
     */
    private function processLineItemTaxes(array $qb_line_items, Invoice $invoice, array $tax_lines): void
    {
        // Get tax_rate_map to find TaxRate details by ID
        $tax_rate_map = $this->service->company->quickbooks->settings->tax_rate_map ?? [];
        $tax_rate_map_by_id = collect($tax_rate_map)->keyBy('id')->toArray();

        // Process each line item
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

            // Check if line item is taxable
            $tax_code_ref = data_get($qb_line_item, 'SalesItemLineDetail.TaxCodeRef.value')
                          ?? data_get($qb_line_item, 'SalesItemLineDetail.TaxCodeRef')
                          ?? data_get($qb_line_item, 'TaxCodeRef.value')
                          ?? data_get($qb_line_item, 'TaxCodeRef');

            // Only apply taxes to taxable line items
            if ($tax_code_ref === 'NON' || empty($tax_code_ref)) {
                // Clear taxes for non-taxable items
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

            // Check for line-item specific tax (if QuickBooks provides it)
            $line_tax_detail = data_get($qb_line_item, 'TaxLineDetail');

            if ($line_tax_detail) {
                // Line item has its own tax detail - process it
                $this->assignTaxesToLineItem($line_item, [$line_tax_detail], $tax_rate_map_by_id, $invoice);
                $line_items_modified = true;
            } elseif (!empty($tax_lines)) {
                // Apply invoice-level taxes to this taxable line item
                // In US tax scenarios, all taxable line items typically get the same set of taxes
                $this->assignTaxesToLineItem($line_item, $tax_lines, $tax_rate_map_by_id, $invoice);
                $line_items_modified = true;
            }

            $line_item_index++;
        }

        // Update invoice line_items if modified
        if ($line_items_modified) {
            $invoice->line_items = $line_items;
        }

        // Clear invoice-level taxes - we ONLY use line-item level taxes
        // This ensures tax-exempt products are correctly excluded and calculations are accurate
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name3 = '';
        $invoice->tax_rate3 = 0;
    }

    /**
     * Assign taxes to a line item.
     *
     * When using QuickBooks AST, all tax details are aggregated into a single tax name and rate.
     *
     * @param object $line_item Invoice Ninja line item
     * @param array $tax_details Array of tax detail objects
     * @param array $tax_rate_map_by_id Tax rate map keyed by ID
     * @param Invoice|null $invoice Invoice for getting client state
     * @return void
     */
    private function assignTaxesToLineItem(object $line_item, array $tax_details, array $tax_rate_map_by_id, ?Invoice $invoice = null): void
    {
        // QB can pass a single object or an array; normalize to array
        if (!empty($tax_details)) {
            if (!is_array($tax_details)) {
                $tax_details = [$tax_details];
            } elseif (!isset($tax_details[0])) {
                $tax_details = [$tax_details];
            }
        }

        // When using QuickBooks AST, always aggregate all tax details into a single tax name and rate
        // This method is only called when AST is enabled, so we always aggregate
        $this->aggregateTaxesForLineItem($line_item, $tax_details, $tax_rate_map_by_id, $invoice);
    }

    /**
     * Aggregate multiple taxes into a single tax rate for a line item.
     *
     * @param object $line_item Invoice Ninja line item
     * @param array $tax_details Array of tax detail objects
     * @param array $tax_rate_map_by_id Tax rate map keyed by ID (unused, kept for compatibility)
     * @param Invoice|null $invoice Invoice for getting client state
     * @return void
     */
    private function aggregateTaxesForLineItem(object $line_item, array $tax_details, array $tax_rate_map_by_id, ?Invoice $invoice = null): void
    {
        $aggregated_rate = $this->calculateAggregatedTaxRate($tax_details, true);
        $tax_name = $this->formatTaxName($aggregated_rate, $invoice);
        
        $this->createTaxRateIfNeeded($tax_name, $aggregated_rate);
        $this->assignTaxToEntity($line_item, $tax_name, $aggregated_rate);
    }

    /**
     * Calculate aggregated tax rate from tax items.
     *
     * @param array $tax_items Array of tax items (can be tax lines or tax details)
     * @param bool $handle_nested Whether to handle nested TaxLineDetail structure
     * @return float Aggregated tax rate percentage
     */
    private function calculateAggregatedTaxRate(array $tax_items, bool $handle_nested = false): float
    {
        $total_tax_percent = 0;
        $total_tax_amount = 0;

        foreach ($tax_items as $tax_item) {
            // Handle both TaxLineDetail structure and direct tax detail
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

        // Use total tax percent or calculate from amount if percent not available
        $aggregated_rate = $total_tax_percent > 0 ? $total_tax_percent : 0;

        // If we can't get rate from percent, calculate from taxable amount
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

    /**
     * Format tax name in "STATE RATE%" format.
     *
     * @param float $rate Tax rate percentage
     * @param Invoice|null $invoice Invoice for getting client state
     * @return string Formatted tax name
     */
    private function formatTaxName(float $rate, ?Invoice $invoice = null): string
    {
        $state = '';
        if ($invoice && $invoice->client) {
            $state = trim($invoice->client->state ?? '');
        }

        return !empty($state) 
            ? "{$state}"
            : "{$rate}%";
    }

    /**
     * Assign aggregated tax to an entity (invoice or line item).
     *
     * @param object|Invoice $entity Invoice or line item object
     * @param string $tax_name Tax name
     * @param float $tax_rate Tax rate percentage
     * @return void
     */
    private function assignTaxToEntity($entity, string $tax_name, float $tax_rate): void
    {
        $entity->tax_name1 = $tax_name;
        $entity->tax_rate1 = round($tax_rate, 2);
        $entity->tax_name2 = '';
        $entity->tax_rate2 = 0;
        $entity->tax_name3 = '';
        $entity->tax_rate3 = 0;
    }

    /**
     * Create or update TaxRate in Invoice Ninja if needed.
     *
     * @param string $tax_name Tax name
     * @param float $tax_rate Tax rate
     * @return void
     */
    private function createTaxRateIfNeeded(string $tax_name, float $tax_rate): void
    {
        if ($tax_rate <= 0) {
            return;
        }

        $ninja_tax_rate = \App\Models\TaxRate::firstOrNew(
            [
                'company_id' => $this->service->company->id,
                'name' => $tax_name,
                'rate' => $tax_rate,
            ]
        );

        // Explicitly set all attributes before saving to ensure they're marked as dirty
        // This ensures all attributes are included in the INSERT statement
        // Pattern matches Helper.php and QuickbooksService.php implementations
        $ninja_tax_rate->company_id = $this->service->company->id;
        $ninja_tax_rate->name = $tax_name;
        $ninja_tax_rate->rate = $tax_rate;
        
        if (!$ninja_tax_rate->exists) {
            $ninja_tax_rate->user_id = $this->service->company->owner()->id;
            $ninja_tax_rate->save();
        }
    }

    /**
     * Clear all tax fields on invoice and line items.
     *
     * @param Invoice $invoice Invoice Ninja invoice
     * @return void
     */
    private function clearAllTaxes(Invoice $invoice): void
    {
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name3 = '';
        $invoice->tax_rate3 = 0;

        // Clear line item taxes
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

    /**
     * qbInvoiceUpdate
     *
     * Updates an invoice in Ninja if the balance is different.
     *
     * @param  array $ninja_invoice_data
     * @param  Invoice $invoice
     * @return void
     */
    private function qbInvoiceUpdate(array $ninja_invoice_data, Invoice $invoice): void
    {
        $current_ninja_invoice_balance = $invoice->balance;
        $qb_invoice_balance = $ninja_invoice_data['balance'];

        if (BcMath::equal($current_ninja_invoice_balance, $qb_invoice_balance)) {
            nlog('Invoice balance is the same, skipping update of line items');
            unset($ninja_invoice_data['line_items']);
            $invoice->fill($ninja_invoice_data);
            $invoice->saveQuietly();
        } else {
            nlog('Invoice balance is different, updating line items');
            $this->invoice_repository->save($ninja_invoice_data, $invoice);
        }
    }

    /**
     * findInvoice
     *
     * Finds an invoice in Ninja by their QuickBooks ID.
     * @param  string $id
     * @param  ?string $client_id
     * @return ?Invoice
     */
    private function findInvoice(string $id, ?string $client_id = null): ?Invoice
    {
        $search = Invoice::query()
                            ->withTrashed()
                            ->where('company_id', $this->service->company->id)
                            ->where('sync->qb_id', $id);

        if ($search->count() == 0) {
            $invoice = InvoiceFactory::create($this->service->company->id, $this->service->company->owner()->id);
            $invoice->client_id = (int) $client_id;

            $sync = new InvoiceSync();
            $sync->qb_id = $id;
            $invoice->sync = $sync;

            return $invoice;
        } elseif ($search->count() == 1) {
            return $this->service->syncable('invoice', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
        }

        return null;

    }

    public function sync($id, string $last_updated): void
    {

        $qb_record = $this->find($id);


        if ($this->service->syncable('invoice', \App\Enum\SyncDirection::PULL)) {

            QuickbooksService::$importing[$this->service->company->id] = true;
            try {
                $invoice = $this->findInvoice($id);

                nlog("Comparing QB last updated: " . $last_updated);
                nlog("Comparing Ninja last updated: " . $invoice->updated_at);

                if (data_get($qb_record, 'TxnStatus') === 'Voided') {
                    $this->delete($id);
                    return;
                }

                if (!$invoice->id) {
                    $this->syncNinjaInvoice($qb_record);
                } elseif (Carbon::parse($last_updated)->gt(Carbon::parse($invoice->updated_at)) || $qb_record->SyncToken == '0') {
                    $ninja_invoice_data = $this->invoice_transformer->qbToNinja($qb_record, $this->service);

                    $this->invoice_repository->save($ninja_invoice_data, $invoice);

                }
            } finally {
                unset(QuickbooksService::$importing[$this->service->company->id]);
            }

        }
    }

    /**
     * syncNinjaInvoice
     *
     * @param  $record
     * @return void
     */
    public function syncNinjaInvoice($record): void
    {

        $ninja_invoice_data = $this->invoice_transformer->qbToNinja($record, $this->service);

        $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];

        $client_id = $ninja_invoice_data['client_id'] ?? null;

        if (is_null($client_id)) {
            return;
        }

        unset($ninja_invoice_data['payment_ids']);

        if ($invoice = $this->findInvoice($ninja_invoice_data['id'], $ninja_invoice_data['client_id'])) {

            if ($invoice->id) {
                $this->qbInvoiceUpdate($ninja_invoice_data, $invoice);
            } elseif (!empty($ninja_invoice_data['number'])) {
                // QB allows duplicate invoice numbers, Ninja does not.
                // Suffix with QB ID to guarantee uniqueness for duplicates.
                if (Invoice::withTrashed()
                    ->where('company_id', $this->service->company->id)
                    ->where('number', $ninja_invoice_data['number'])
                    ->exists()) {
                    $ninja_invoice_data['number'] = $ninja_invoice_data['number'] . '_' . $ninja_invoice_data['id'];
                }
            }

            $invoice->fill($ninja_invoice_data);
            $invoice->saveQuietly();

            $invoice = $invoice->calc()->getInvoice()->service()->markSent()->applyNumber()->createInvitations()->save();

            foreach ($payment_ids as $payment_id) {

                $payment = $this->service->sdk->FindById('Payment', $payment_id);

                $payment_transformer = new PaymentTransformer($this->service->company);

                $transformed = $payment_transformer->qbToNinja($payment);

                $ninja_payment = $payment_transformer->buildPayment($payment);
                $ninja_payment->service()->applyNumber()->save();

                $paymentable = new \App\Models\Paymentable();
                $paymentable->payment_id = $ninja_payment->id;
                $paymentable->paymentable_id = $invoice->id;
                $paymentable->paymentable_type = 'invoices';
                $paymentable->amount = $transformed['applied'] + $ninja_payment->credits->sum('amount');
                $paymentable->created_at = $ninja_payment->date; //@phpstan-ignore-line
                $paymentable->save();

                $invoice->service()->applyPayment($ninja_payment, $paymentable->amount);

            }

            if ($record instanceof \QuickBooksOnline\API\Data\IPPSalesReceipt) {
                $invoice->service()->markPaid()->save();
            }

        }

    }

    /**
     * Deletes the invoice from Ninja and sets the sync to null
     *
     * @param string $id
     * @return void
     */
    public function delete($id): void
    {
        $qb_record = $this->find($id);

        if ($this->service->syncable('invoice', \App\Enum\SyncDirection::PULL) && $invoice = $this->findInvoice($id)) {
            QuickbooksService::$importing[$this->service->company->id] = true;
            try {
                $this->invoice_repository->delete($invoice);
            } finally {
                unset(QuickbooksService::$importing[$this->service->company->id]);
            }
        }
    }

    /**
     * Validate and sync invoice amounts with QuickBooks to ensure they match.
     *
     * This method checks:
     * 1. Total tax amounts (TotalTax)
     * 2. Total invoice amounts (TotalAmt vs amount field)
     * 3. Subtotal amounts (sum of line items)
     *
     * Note: Balance is not compared as it should be calculated from amount - paid_to_date.
     *
     * If mismatches are found, it adjusts Invoice Ninja amounts to match QuickBooks.
     *
     * @param mixed $qb_response QuickBooks invoice response
     * @param Invoice $invoice Invoice Ninja invoice
     * @return void
     */
    private function validateAndSyncAmounts(mixed $qb_response, Invoice $invoice): void
    {
        // Get QuickBooks amounts
        $qb_total_tax = (float) data_get($qb_response, 'TxnTaxDetail.TotalTax', 0);
        $qb_total_amt = (float) data_get($qb_response, 'TotalAmt', 0);

        // Calculate QuickBooks subtotal (TotalAmt - TotalTax)
        $qb_subtotal = $qb_total_amt - $qb_total_tax;

        // Get Invoice Ninja amounts from calculation
        $invoice_calc = $invoice->calc();
        $ninja_total_tax = (float) $invoice->total_taxes;
        $ninja_total_amt = (float) $invoice->amount;
        $ninja_subtotal = (float) $invoice_calc->getSubTotal();

        // Tolerance for rounding differences (0.01)
        $tolerance = 0.01;
        $mismatches = [];

        // Check tax amounts
        if (abs($qb_total_tax - $ninja_total_tax) > $tolerance) {
            $mismatches[] = [
                'type' => 'tax',
                'qb' => $qb_total_tax,
                'ninja' => $ninja_total_tax,
                'difference' => abs($qb_total_tax - $ninja_total_tax),
            ];
            nlog("QuickBooks: Invoice {$invoice->id} tax amount mismatch - QB: {$qb_total_tax}, Ninja: {$ninja_total_tax}, Difference: " . abs($qb_total_tax - $ninja_total_tax));
        }

        // Check total amounts (TotalAmt vs amount field)
        if (abs($qb_total_amt - $ninja_total_amt) > $tolerance) {
            $mismatches[] = [
                'type' => 'amount',
                'qb' => $qb_total_amt,
                'ninja' => $ninja_total_amt,
                'difference' => abs($qb_total_amt - $ninja_total_amt),
            ];
            nlog("QuickBooks: Invoice {$invoice->id} amount mismatch - QB TotalAmt: {$qb_total_amt}, Ninja amount: {$ninja_total_amt}, Difference: " . abs($qb_total_amt - $ninja_total_amt));
        }

        // Check subtotals (with larger tolerance as this might include discounts/surcharges)
        if (abs($qb_subtotal - $ninja_subtotal) > ($tolerance * 2)) {
            $mismatches[] = [
                'type' => 'subtotal',
                'qb' => $qb_subtotal,
                'ninja' => $ninja_subtotal,
                'difference' => abs($qb_subtotal - $ninja_subtotal),
            ];
            nlog("QuickBooks: Invoice {$invoice->id} subtotal mismatch - QB: {$qb_subtotal}, Ninja: {$ninja_subtotal}, Difference: " . abs($qb_subtotal - $ninja_subtotal));
        }

        // If there are mismatches, sync Invoice Ninja amounts to match QuickBooks
        if (!empty($mismatches)) {
            nlog("QuickBooks: Syncing Invoice {$invoice->id} amounts to match QuickBooks. Mismatches: " . json_encode($mismatches));

            // Sync tax amount
            if (abs($qb_total_tax - $ninja_total_tax) > $tolerance) {
                $invoice->total_taxes = round($qb_total_tax, 2);
            }

            // Sync amount field (not balance - balance is calculated from amount - paid_to_date)
            if (abs($qb_total_amt - $ninja_total_amt) > $tolerance) {
                $invoice->amount = round($qb_total_amt, 2);
                // Recalculate balance after updating amount
                $invoice = $invoice->calc()->getInvoice();
            }

            nlog("QuickBooks: Invoice {$invoice->id} amounts synced - Tax: {$invoice->total_taxes}, Amount: {$invoice->amount}, Balance: {$invoice->balance}");
        } else {
            nlog("QuickBooks: Invoice {$invoice->id} amounts validated - all match QuickBooks");
        }
    }
}
