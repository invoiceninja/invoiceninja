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
use App\Interfaces\SyncInterface;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use App\Services\Quickbooks\Invoice\AstTaxResponseProcessor;
use App\Services\Quickbooks\Invoice\AttachInvoicePayments;
use App\Services\Quickbooks\Invoice\CheckInvoice;
use App\Services\Quickbooks\Invoice\InvoiceCollisionPolicy;
use App\Services\Quickbooks\Invoice\InvoiceLookup;
use App\Services\Quickbooks\Invoice\InvoiceSyncStatus;
use App\Services\Quickbooks\QuickbooksFaultParser;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use Carbon\Carbon;
use RuntimeException;

class QbInvoice implements SyncInterface
{
    protected InvoiceTransformer $invoice_transformer;

    protected InvoiceRepository $invoice_repository;

    private InvoiceCollisionPolicy $collision;

    private InvoiceSyncStatus $sync_status;

    private AstTaxResponseProcessor $ast;

    private AttachInvoicePayments $payments;

    private CheckInvoice $checker;

    public function __construct(public QuickbooksService $service)
    {
        $this->invoice_transformer = new InvoiceTransformer($this->service->company);
        $this->invoice_repository = new InvoiceRepository();
        $this->sync_status = new InvoiceSyncStatus();
        $this->collision = new InvoiceCollisionPolicy($this->service, $this->lookup());
        $this->ast = new AstTaxResponseProcessor($this->service);
        $this->payments = new AttachInvoicePayments($this->service);
        $this->checker = new CheckInvoice($this->service, $this->collision);
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk()->findById('Invoice', $id);
    }

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
                    $invoice = $invoice->calc()->getInvoice()->service()->markSent()->applyNumber()->createInvitations()->save();
                    $this->markInvoiceSynced($invoice, (string) $ninja_invoice_data['id'], (string) data_get($record, 'SyncToken', ''));

                    if ($record instanceof \QuickBooksOnline\API\Data\IPPSalesReceipt) {
                        $invoice->service()->markPaid()->save();
                    }
                }
            }
        }
    }

    public function syncToForeign(array $records): void
    {
        foreach ($records as $invoice) {
            if (!$invoice instanceof Invoice) {
                continue;
            }

            $operation = 'preparing the invoice';

            try {
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

                if (!$is_linked && !empty($invoice->number)) {
                    $remote = $this->findQbInvoiceByDocNumber((string) $invoice->number);
                    if ($remote) {
                        $this->flagNumberCollision($invoice, $remote);
                        nlog("QuickBooks: Push create blocked for invoice {$invoice->id} — DocNumber collision with QB Id " . data_get($remote, 'Id'));
                        continue;
                    }
                }

                $operation = 'preparing the invoice';
                $qb_invoice_data = $this->invoice_transformer->ninjaToQb($invoice, $this->service);

                if ($is_linked) {
                    $operation = 'fetching the current invoice';
                    $existing_qb_invoice = $this->find($invoice_qb_id);
                    if ($existing_qb_invoice) {
                        $qb_invoice_data['SyncToken'] = $existing_qb_invoice->SyncToken ?? '0';
                    }
                }

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

        if (!$this->customerMatches($invoice, $qb_record)) {
            $this->flagNumberCollision($invoice, $qb_record);
            throw new RuntimeException('QuickBooks invoice belongs to a different customer; cannot force-link.');
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

    public function check(Invoice $invoice): Invoice
    {
        return $this->checker->handle($invoice);
    }

    public function checkContext(): array
    {
        return $this->checker->context();
    }

    public function attachPayments(Invoice $invoice, array $payment_ids): void
    {
        $this->payments->attach($invoice, $payment_ids);
    }

    public function delete($id): void
    {
        $this->find($id);

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
     * @param  array<string, mixed>  $ninja_invoice_data
     */
    private function qbInvoiceUpdate(array $ninja_invoice_data, Invoice $invoice): void
    {
        $this->lookup()->updateFromQuickbooks($ninja_invoice_data, $invoice);
    }

    private function findInvoice(string $id, ?string $client_id = null): ?Invoice
    {
        return $this->lookup()->findByQbId($id, $client_id);
    }

    /**
     * @param  array<string, mixed>  $ninja_invoice_data
     */
    private function handlePullNumberCollision(array $ninja_invoice_data, mixed $qb_record): bool
    {
        return $this->collision->handlePullNumberCollision($ninja_invoice_data, $qb_record);
    }

    private function findQbInvoiceByDocNumber(string $doc_number, bool $fail_open = true): mixed
    {
        return $this->collision->findQbInvoiceByDocNumber($doc_number, $fail_open);
    }

    private function amountsMatch(mixed $qb_record, Invoice $invoice): bool
    {
        return $this->collision->amountsMatch($qb_record, $invoice);
    }

    private function customerMatches(Invoice $invoice, mixed $qb_record): bool
    {
        return $this->collision->customerMatches($invoice, $qb_record);
    }

    private function flagNumberCollision(Invoice $invoice, mixed $qb_record): void
    {
        $this->collision->flagNumberCollision($invoice, $qb_record);
    }

    private function markInvoiceSynced(
        Invoice $invoice,
        string $qb_id,
        string $sync_token = '',
        bool $clear_status_message = false
    ): void {
        $this->sync_status->markSynced($invoice, $qb_id, $sync_token, $clear_status_message);
    }

    private function markInvoicePushFailure(Invoice $invoice, string $message): void
    {
        $this->sync_status->markPushFailure($invoice, $message);
    }

    private function processQuickbooksTaxResponse(mixed $qb_response, Invoice $invoice): void
    {
        $this->ast->process($qb_response, $invoice);
    }

    private function lookup(): InvoiceLookup
    {
        return new InvoiceLookup($this->service->company, $this->invoice_repository);
    }
}
