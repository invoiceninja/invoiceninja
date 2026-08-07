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

use App\DataMapper\InvoiceSync;
use App\Enum\InvoiceQbStatus;
use App\Factory\InvoiceFactory;
use App\Interfaces\SyncInterface;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\QuickbooksFaultParser;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;
use App\Utils\BcMath;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use RuntimeException;

class QbInvoice implements SyncInterface
{
    use MakesHash;

    protected InvoiceTransformer $invoice_transformer;

    protected InvoiceRepository $invoice_repository;

    protected array $check_context = [];

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
        return $this->service->sdk()->findById('Invoice', $id);
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

            if ($ninja_invoice_data === false) {
                continue;
            }

            $client_id = $ninja_invoice_data['client_id'] ?? null;

            if (is_null($client_id)) {
                nlog("QuickBooks importToNinja: Skipping invoice — client could not be resolved");
                continue;
            }

            unset($ninja_invoice_data['payment_ids']);

            if ($invoice = $this->findInvoice($ninja_invoice_data['id'], $ninja_invoice_data['client_id'])) {

                if ($invoice->id) {
                    $this->qbInvoiceUpdate($ninja_invoice_data, $invoice);
                    $this->markInvoiceSynced($invoice->fresh(), (string) $ninja_invoice_data['id'], (string) data_get($record, 'SyncToken', ''));
                } else {
                    if ($this->handlePullNumberCollision($ninja_invoice_data, $record)) {
                        continue;
                    }

                    $invoice->fill($ninja_invoice_data);
                    $invoice->saveQuietly();

                    // During QB import, use saveQuietly() to prevent circular sync back to QuickBooks
                    $invoice = $invoice->calc()->getInvoice()->service()->markSent()->applyNumber()->createInvitations()->save();
                    $this->markInvoiceSynced($invoice, (string) $ninja_invoice_data['id'], (string) data_get($record, 'SyncToken', ''));

                    if ($record instanceof \QuickBooksOnline\API\Data\IPPSalesReceipt) {
                        // During QB import, use saveQuietly() to prevent circular sync back to QuickBooks
                        $invoice->service()->markPaid()->save();
                    }
                }

            }

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

            $operation = 'preparing the invoice';

            try {
                // Ensure client exists in QuickBooks before pushing the invoice
                $client = $invoice->client;
                if (empty($client->sync->qb_id)) {
                    $operation = 'creating the related customer';
                    $qb_client_id = $this->service->client->createQbClient($client);
                    if (empty($qb_client_id)) {
                        nlog("QuickBooks: Skipping invoice {$invoice->id} — unable to create client {$client->id} in QuickBooks");
                        $this->markInvoicePushFailure($invoice, "Unable to push to QuickBooks: client could not be created.");
                        continue;
                    }
                    $client->refresh();
                }

                $invoice_qb_id = (string) data_get($invoice->sync, 'qb_id', '');
                $is_linked = $invoice_qb_id !== '';

                // Create path: DocNumber collision becomes linkable/amount_mismatch — never add a second QB invoice.
                if (!$is_linked && !empty($invoice->number)) {
                    $remote = $this->findQbInvoiceByDocNumber((string) $invoice->number);
                    if ($remote) {
                        $this->flagNumberCollision($invoice, $remote);
                        nlog("QuickBooks: Push create blocked for invoice {$invoice->id} — DocNumber collision with QB Id " . data_get($remote, 'Id'));
                        continue;
                    }
                }

                // Transform invoice to QuickBooks format
                $operation = 'preparing the invoice';
                $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->service);

                // If updating, fetch SyncToken using existing find() method
                if ($is_linked) {
                    $operation = 'fetching the current invoice';
                    $existing_qb_invoice = $this->find($invoice_qb_id);
                    if ($existing_qb_invoice) {
                        $qb_invoice_data['SyncToken'] = $existing_qb_invoice->SyncToken ?? '0';
                    }
                }

                // Create or update invoice in QuickBooks
                $qb_invoice = \QuickBooksOnline\API\Facades\Invoice::create($qb_invoice_data);

                $result = false;

                nlog("QuickBooks: Pushing invoice {$invoice->id} payload", ['data' => $qb_invoice_data]);

                if ($is_linked) {
                    $operation = 'updating the invoice';
                    $result = $this->service->sdk()->update($qb_invoice);
                    nlog("QuickBooks: Updated invoice {$invoice->id} (QB ID: {$invoice_qb_id})", [
                        'result_id' => data_get($result, 'Id'),
                    ]);
                } else {
                    $operation = 'creating the invoice';
                    $result = $this->service->sdk()->add($qb_invoice);
                    nlog("QuickBooks: Created invoice {$invoice->id} (QB ID: " . (data_get($result, 'Id') ?? data_get($result, 'Id.value')) . ")");
                }

                $qb_id = (string) (data_get($result, 'Id') ?? data_get($result, 'Id.value') ?? '');
                $sync_token = (string) (data_get($result, 'SyncToken') ?? '');

                if ($qb_id !== '') {
                    $this->markInvoiceSynced($invoice, $qb_id, $sync_token, true);
                }

                // Process QuickBooks AST response: extract tax details, create missing tax rates, and sync totals
                // Only process if we have a valid result with an ID and automatic taxes are enabled
                if ($qb_id && ($this->service->company->quickbooks->settings->automatic_taxes ?? false)) {
                    $this->processQuickbooksTaxResponse($result, $invoice);
                }

            } catch (\Throwable $e) {
                $message = (new QuickbooksFaultParser())->statusMessage($e, $operation);

                nlog("QuickBooks: Error pushing invoice {$invoice->id} to QuickBooks: {$e->getMessage()}", [
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->markInvoicePushFailure($invoice, $message);
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

            $balance_before = (float) $invoice->balance;

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

            $this->applyAstClientBalanceAdjustment($invoice, $balance_before);

            $invoice->saveQuietly();

        } catch (\Exception $e) {
            nlog("QuickBooks: Error processing tax response for invoice {$invoice->id}: {$e->getMessage()}");
        }
    }

    /**
     * Correct client balance and ledger when AST changes a sent invoice after mark-sent.
     *
     * Drafts are ignored — mark-sent will apply the post-AST balance when the invoice is sent.
     */
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
        if ($invoice) {
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
            $invoice->design_id = $this->decodePrimaryKey($this->service->company->settings->invoice_design_id);

            $sync = new InvoiceSync();
            $sync->markSynced($id);
            $invoice->sync = $sync;

            return $invoice;
        }

        return $search->first();
    }

    public function sync($id, string $last_updated): void
    {

        $qb_record = $this->find($id);


        if ($this->service->syncable('invoice', \App\Enum\SyncDirection::PULL)) {

            QuickbooksService::$importing[$this->service->company->id] = true;
            try {
                $invoice = $this->findInvoice($id);

                // /** returns immediately if invoice is found */
                // if($invoice->id)
                //     return;

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

                    $invoice = $this->invoice_repository->save($ninja_invoice_data, $invoice);

                    if ($invoice) {
                        $this->markInvoiceSynced($invoice, (string) $id, (string) data_get($qb_record, 'SyncToken', ''));
                    }
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

        if ($ninja_invoice_data === false) {
            return;
        }

        $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];

        $client_id = $ninja_invoice_data['client_id'] ?? null;

        if (is_null($client_id)) {
            nlog("QuickBooks syncNinjaInvoice: Skipping invoice — client could not be resolved");
            return;
        }

        unset($ninja_invoice_data['payment_ids']);

        if ($invoice = $this->findInvoice($ninja_invoice_data['id'], $ninja_invoice_data['client_id'])) {

            if ($invoice->id) {
                $this->qbInvoiceUpdate($ninja_invoice_data, $invoice);
                $this->markInvoiceSynced($invoice, (string) $ninja_invoice_data['id'], (string) data_get($record, 'SyncToken', ''));
                $this->attachPayments($invoice, $payment_ids);

                return;
            }

            if ($this->handlePullNumberCollision($ninja_invoice_data, $record)) {
                return;
            }

            $invoice->fill($ninja_invoice_data);
            $invoice->saveQuietly();

            $invoice = $invoice->calc()->getInvoice()->service()->markSent()->applyNumber()->createInvitations()->save();
            $this->markInvoiceSynced($invoice, (string) $ninja_invoice_data['id'], (string) data_get($record, 'SyncToken', ''));

            $this->attachPayments($invoice, $payment_ids);

            if ($record instanceof \QuickBooksOnline\API\Data\IPPSalesReceipt) {
                $invoice->service()->markPaid()->save();
            }

        }

    }

    /**
     * Force-link an unlinked Ninja invoice to a matching QuickBooks invoice by DocNumber.
     * QB overwrites invoice fields; linked QB payments are imported without deleting Ninja payments.
     */
    public function forceLink(Invoice $invoice): Invoice
    {
        if (!empty($invoice->sync->qb_id ?? null)) {
            throw new RuntimeException('Invoice is already linked to QuickBooks and cannot be relinked.');
        }

        if (empty($invoice->number)) {
            throw new RuntimeException('Invoice number is required to force-link.');
        }

        $qb_record = $this->findQbInvoiceByDocNumber((string) $invoice->number);

        if (!$qb_record) {
            throw new RuntimeException('No QuickBooks invoice found with matching DocNumber.');
        }

        if (!$this->amountsMatch($qb_record, $invoice)) {
            $this->flagNumberCollision($invoice, $qb_record);
            throw new RuntimeException('QuickBooks invoice amount does not match; cannot force-link.');
        }

        $qb_id = (string) data_get($qb_record, 'Id');
        $ninja_invoice_data = $this->invoice_transformer->qbToNinja($qb_record, $this->service);

        if ($ninja_invoice_data === false) {
            throw new RuntimeException('Unable to transform QuickBooks invoice for force-link.');
        }

        $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];
        unset($ninja_invoice_data['payment_ids'], $ninja_invoice_data['id']);

        QuickbooksService::$importing[$this->service->company->id] = true;

        try {
            $invoice->fill($ninja_invoice_data);
            $invoice->saveQuietly();
            $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

            $this->markInvoiceSynced($invoice, $qb_id, (string) data_get($qb_record, 'SyncToken', ''), true);
            $this->attachPayments($invoice->fresh(), $payment_ids);
        } finally {
            unset(QuickbooksService::$importing[$this->service->company->id]);
        }

        return $invoice->fresh();
    }

    /**
     * Force-pull from the linked QuickBooks invoice onto Ninja (same qb_id).
     */
    public function forcePull(Invoice $invoice): Invoice
    {
        $qb_id = (string) data_get($invoice->sync, 'qb_id', '');

        if ($qb_id === '') {
            throw new RuntimeException('Invoice is not linked to QuickBooks.');
        }

        if (!$this->service->syncable('invoice', \App\Enum\SyncDirection::PULL)) {
            throw new RuntimeException('Invoice pull is not enabled for this company.');
        }

        $qb_record = $this->find($qb_id);

        if (!$qb_record) {
            throw new RuntimeException("QuickBooks invoice {$qb_id} was not found.");
        }

        if (data_get($qb_record, 'TxnStatus') === 'Voided') {
            $this->delete($qb_id);
            return $invoice->fresh();
        }

        $ninja_invoice_data = $this->invoice_transformer->qbToNinja($qb_record, $this->service);

        if ($ninja_invoice_data === false) {
            throw new RuntimeException('Unable to transform QuickBooks invoice for force-pull.');
        }

        $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];
        unset($ninja_invoice_data['payment_ids'], $ninja_invoice_data['id']);

        QuickbooksService::$importing[$this->service->company->id] = true;

        try {
            $invoice = $this->invoice_repository->save($ninja_invoice_data, $invoice);
            $this->markInvoiceSynced($invoice, $qb_id, (string) data_get($qb_record, 'SyncToken', ''));
            $this->attachPayments($invoice, $payment_ids);

        } finally {
            unset(QuickbooksService::$importing[$this->service->company->id]);
        }

        return $invoice->fresh();
    }

    /**
     * Retry a previously failed IN→QB push. Requires a non-empty status message on syncable/synced.
     */
    public function forcePush(Invoice $invoice): Invoice
    {
        $sync = $invoice->sync ?? new InvoiceSync();
        $status = $sync->status();

        if (!in_array($status, [InvoiceQbStatus::Syncable, InvoiceQbStatus::Synced], true)) {
            throw new RuntimeException('Force-push is only available for syncable or synced invoices with a prior push failure.');
        }

        if ($sync->qb_status_message === '') {
            throw new RuntimeException('Force-push is only available after a recorded push failure.');
        }

        if (!$this->service->syncable('invoice', \App\Enum\SyncDirection::PUSH)) {
            throw new RuntimeException('Invoice push is not enabled for this company.');
        }

        $this->syncToForeign([$invoice]);

        return $invoice->fresh();
    }

    /**
     * Check the current QuickBooks invoice without changing invoice or payment data.
     */
    public function check(Invoice $invoice): Invoice
    {
        $this->check_context = [];
        $qb_id = (string) data_get($invoice->sync, 'qb_id', '');

        if ($qb_id === '') {
            return $this->checkUnlinkedInvoice($invoice);
        }

        $qb_record = $this->find($qb_id);
        $sync = $invoice->sync ?? new InvoiceSync();
        $previous_status = $sync->status();

        if (!$qb_record) {
            $sync->markSynced($qb_id, $sync->qb_sync_token, false);
            $sync->markPushFailure("QuickBooks invoice {$qb_id} was not found.");
            $invoice->sync = $sync;
            $invoice->saveQuietly();

            $invoice = $invoice->fresh();
            $this->check_context = $this->buildCheckContext($invoice, null, true, 'not_found');

            return $invoice;
        }

        $sync->markSynced($qb_id, (string) data_get($qb_record, 'SyncToken', ''), false);

        if ($message = $this->linkedInvoiceCheckMessage($invoice, $qb_record)) {
            if (data_get($qb_record, 'TxnStatus') === 'Voided') {
                $sync->markPushFailure($message);
            } else {
                $sync->markDataMismatch($message);
            }
        } elseif (in_array($previous_status, [InvoiceQbStatus::DataMismatch, InvoiceQbStatus::AmountMismatch], true)) {
            $sync->clearStatusMessage();
        }

        $invoice->sync = $sync;
        $invoice->saveQuietly();

        $invoice = $invoice->fresh();
        $outcome = match (true) {
            data_get($qb_record, 'TxnStatus') === 'Voided' => 'voided',
            $invoice->sync->status() === InvoiceQbStatus::DataMismatch => InvoiceQbStatus::DataMismatch->value,
            default => InvoiceQbStatus::Synced->value,
        };
        $this->check_context = $this->buildCheckContext($invoice, $qb_record, true, $outcome);

        return $invoice;
    }

    public function checkContext(): array
    {
        return $this->check_context;
    }

    private function checkUnlinkedInvoice(Invoice $invoice): Invoice
    {
        $sync = $invoice->sync ?? new InvoiceSync();

        if (empty($invoice->number)) {
            $sync->markSyncable();
            $sync->markPushFailure('Invoice number is required to check QuickBooks.');
            $invoice->sync = $sync;
            $invoice->saveQuietly();

            $invoice = $invoice->fresh();
            $this->check_context = $this->buildCheckContext($invoice, null, false, InvoiceQbStatus::Syncable->value);

            return $invoice;
        }

        $qb_record = $this->findQbInvoiceByDocNumber((string) $invoice->number, false);

        if ($qb_record) {
            $this->flagNumberCollision($invoice, $qb_record);
        } else {
            $clear_status_message = in_array(
                $sync->status(),
                [InvoiceQbStatus::Linkable, InvoiceQbStatus::DataMismatch, InvoiceQbStatus::AmountMismatch],
                true
            );

            $sync->markSyncable($clear_status_message);
            $invoice->sync = $sync;
            $invoice->saveQuietly();
        }

        $invoice = $invoice->fresh();
        $outcome = $invoice->sync->status()->value;
        $this->check_context = $this->buildCheckContext($invoice, $qb_record, false, $outcome);

        return $invoice;
    }

    private function buildCheckContext(Invoice $invoice, mixed $qb_record, bool $linked, string $outcome): array
    {
        $quickbooks = null;
        $comparison = null;

        if ($qb_record) {
            $qb_number = (string) data_get($qb_record, 'DocNumber', '');
            $qb_total = (float) data_get($qb_record, 'TotalAmt', 0);

            $quickbooks = [
                'id' => (string) data_get($qb_record, 'Id', ''),
                'number' => $qb_number,
                'total' => $qb_total,
                'balance' => (float) data_get($qb_record, 'Balance', 0),
                'status' => (string) data_get($qb_record, 'TxnStatus', ''),
                'sync_token' => (string) data_get($qb_record, 'SyncToken', ''),
                'last_updated_at' => (string) data_get($qb_record, 'MetaData.LastUpdatedTime', ''),
            ];
            $comparison = [
                'number' => [
                    'matches' => $qb_number === (string) $invoice->number,
                    'invoice_ninja' => (string) $invoice->number,
                    'quickbooks' => $qb_number,
                ],
                'total' => [
                    'matches' => $this->amountsMatch($qb_record, $invoice),
                    'invoice_ninja' => (float) $invoice->amount,
                    'quickbooks' => $qb_total,
                ],
            ];
        }

        return [
            'outcome' => $outcome,
            'linked' => $linked,
            'message' => (string) data_get($invoice->sync, 'qb_status_message', ''),
            'checked_at' => now()->toIso8601String(),
            'quickbooks' => $quickbooks,
            'comparison' => $comparison,
            'recommended_actions' => $this->recommendedCheckActions($invoice, $outcome, $linked),
        ];
    }

    private function recommendedCheckActions(Invoice $invoice, string $outcome, bool $linked): array
    {
        if ($outcome === InvoiceQbStatus::Syncable->value) {
            return !empty($invoice->number)
                && !empty($invoice->sync->qb_status_message)
                && $this->service->syncable('invoice', \App\Enum\SyncDirection::PUSH)
                    ? ['force_push']
                    : [];
        }

        if ($outcome === InvoiceQbStatus::DataMismatch->value && $linked) {
            $actions = ['verify_quickbooks_invoice'];

            if ($this->service->syncable('invoice', \App\Enum\SyncDirection::PULL)) {
                $actions[] = 'force_pull';
            }

            return $actions;
        }

        return match ($outcome) {
            InvoiceQbStatus::Linkable->value => ['verify_quickbooks_invoice', 'force_link'],
            InvoiceQbStatus::DataMismatch->value => ['verify_quickbooks_invoice', 'change_invoice_number'],
            'not_found', 'voided' => ['verify_quickbooks_invoice'],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $ninja_invoice_data
     */
    private function handlePullNumberCollision(array $ninja_invoice_data, mixed $qb_record): bool
    {
        $number = $ninja_invoice_data['number'] ?? null;

        if (empty($number)) {
            return false;
        }

        $existing = $this->findInvoiceByNumber((string) $number);

        if (!$existing) {
            return false;
        }

        // Locked link on another row — do not create a duplicate and do not rewrite that link.
        if (!empty($existing->sync->qb_id ?? false)) {
            nlog('QuickBooks: Skipping create — DocNumber owned by linked invoice', [
                'number' => $number,
                'existing_invoice_id' => $existing->id,
                'existing_qb_id' => $existing->sync->qb_id ?? null,
                'incoming_qb_id' => $ninja_invoice_data['id'] ?? null,
            ]);

            return true;
        }

        $this->flagNumberCollision($existing, $qb_record);

        nlog('QuickBooks: Skipping create — DocNumber collision flagged on existing invoice', [
            'number' => $number,
            'existing_invoice_id' => $existing->id,
            'qb_status' => data_get($existing->sync, 'qb_status'),
            'incoming_qb_id' => $ninja_invoice_data['id'] ?? null,
        ]);

        return true;
    }

    private function findInvoiceByNumber(string $number): ?Invoice
    {
        if ($number === '') {
            return null;
        }

        return Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->service->company->id)
            ->where('number', $number)
            ->first();
    }

    private function findQbInvoiceByDocNumber(string $doc_number, bool $fail_open = true): mixed
    {
        if ($doc_number === '') {
            return null;
        }

        try {
            $escaped = str_replace("'", "\\'", $doc_number);
            $result = $this->service->sdk()->query("select * from Invoice where DocNumber = '{$escaped}'");
        } catch (\Throwable $e) {
            if (!$fail_open) {
                throw $e;
            }

            nlog("QuickBooks: DocNumber preflight failed for '{$doc_number}', proceeding with create: {$e->getMessage()}");

            return null;
        }

        if (empty($result)) {
            return null;
        }

        if (is_array($result)) {
            return $result[0] ?? null;
        }

        return $result;
    }

    private function amountsMatch(mixed $qb_record, Invoice $invoice): bool
    {
        $qb_total = (float) data_get($qb_record, 'TotalAmt', 0);

        return abs($qb_total - (float) $invoice->amount) <= 0.01;
    }

    private function linkedInvoiceCheckMessage(Invoice $invoice, mixed $qb_record): ?string
    {
        if (data_get($qb_record, 'TxnStatus') === 'Voided') {
            return 'The linked QuickBooks invoice is voided.';
        }

        $qb_number = (string) data_get($qb_record, 'DocNumber', '');
        $ninja_number = (string) $invoice->number;
        $number_differs = $qb_number !== $ninja_number;
        $amount_differs = !$this->amountsMatch($qb_record, $invoice);

        if (!$number_differs && !$amount_differs) {
            return null;
        }

        $qb_total = number_format((float) data_get($qb_record, 'TotalAmt', 0), 2, '.', '');
        $ninja_total = number_format((float) $invoice->amount, 2, '.', '');

        $message = match (true) {
            $number_differs && $amount_differs
                => "The linked QuickBooks invoice differs: its number is #{$qb_number} instead of #{$ninja_number}, and its total is {$qb_total} instead of {$ninja_total}.",
            $number_differs
                => "The linked QuickBooks invoice number is #{$qb_number}, while Invoice Ninja uses #{$ninja_number}.",
            default
                => "The linked QuickBooks invoice total is {$qb_total}, while Invoice Ninja uses {$ninja_total}.",
        };

        return mb_substr($message, 0, 255);
    }

    private function flagNumberCollision(Invoice $invoice, mixed $qb_record): void
    {
        // qb_id set => locked link; never re-flag as linkable.
        if (!empty($invoice->sync->qb_id ?? null)) {
            return;
        }

        $qb_id = (string) data_get($qb_record, 'Id', '');
        $doc_number = (string) data_get($qb_record, 'DocNumber', $invoice->number ?? '');
        $qb_total = number_format((float) data_get($qb_record, 'TotalAmt', 0), 2, '.', '');
        $ninja_total = number_format((float) $invoice->amount, 2, '.', '');

        $sync = $invoice->sync ?? new InvoiceSync();

        if ($this->amountsMatch($qb_record, $invoice)) {
            $sync->markLinkable(
                "QuickBooks invoice #{$doc_number} (Id {$qb_id}) has the same number and total ({$qb_total}). Verify it is the same invoice before linking."
            );
        } else {
            $sync->markDataMismatch(
                "Invoice number #{$doc_number} is already used by a QuickBooks invoice with a different total. QuickBooks has {$qb_total}; Invoice Ninja has {$ninja_total}. Verify the records or change the Invoice Ninja invoice number and retry."
            );
        }

        $invoice->sync = $sync;
        $invoice->saveQuietly();
    }

    private function markInvoiceSynced(
        Invoice $invoice,
        string $qb_id,
        string $sync_token = '',
        bool $clear_status_message = false
    ): void
    {
        $sync = $invoice->sync ?? new InvoiceSync();
        $sync->markSynced($qb_id, $sync_token, $clear_status_message);
        $invoice->sync = $sync;
        $invoice->saveQuietly();
    }

    private function markInvoicePushFailure(Invoice $invoice, string $message): void
    {
        $sync = $invoice->sync ?? new InvoiceSync();
        $sync->markPushFailure($message);
        $invoice->sync = $sync;
        $invoice->saveQuietly();
    }

    /**
     * attachPayments
     *
     * Reconciles the QuickBooks payments linked to an invoice into Ninja.
     *
     * For each QB payment id, ensures a Ninja payment exists — idempotent via
     * PaymentTransformer::buildPayment(), which dedupes on sync->qb_id — and
     * links it to the invoice, skipping any Paymentable that already exists.
     * Safe to call repeatedly and on already-synced invoices: it only creates
     * missing payment links and never modifies the invoice's own fields.
     *
     * @param  Invoice $invoice     The Ninja invoice (must carry sync->qb_id).
     * @param  array   $payment_ids QuickBooks Payment ids linked to the invoice.
     * @return void
     */
    public function attachPayments(Invoice $invoice, array $payment_ids): void
    {
        $payment_transformer = new PaymentTransformer($this->service->company);

        foreach ($payment_ids as $payment_id) {

            if (!$payment_id) {
                continue;
            }

            $payment = $this->service->sdk()->findById('Payment', $payment_id);

            $ninja_payment = $payment_transformer->buildPayment($payment);
            $ninja_payment->service()->applyNumber()->save();

            $exists = \App\Models\Paymentable::withTrashed()
                ->where('payment_id', $ninja_payment->id)
                ->where('paymentable_id', $invoice->id)
                ->where('paymentable_type', 'invoices')
                ->exists();

            if ($exists) {
                continue;
            }

            $amount = $payment_transformer->appliedAmountForInvoice(
                $payment,
                (string) data_get($invoice->sync, 'qb_id', '')
            );

            if ($amount <= 0) {
                continue;
            }

            $paymentable = new \App\Models\Paymentable();
            $paymentable->payment_id = $ninja_payment->id;
            $paymentable->paymentable_id = $invoice->id;
            $paymentable->paymentable_type = 'invoices';
            $paymentable->amount = $amount;
            $timezone = $this->service->company->timezone()?->name ?: config('app.timezone');
            $paymentable->created_at = app(\App\Services\Payment\PaymentApplicationDateResolver::class)
                ->encodeBusinessDate($ninja_payment->date, $timezone);
            $paymentable->save();

            $invoice->service()->applyPayment($ninja_payment, $paymentable->amount);

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
