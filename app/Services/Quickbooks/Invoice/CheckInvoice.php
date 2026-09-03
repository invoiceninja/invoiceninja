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

use App\DataMapper\InvoiceSync;
use App\Enum\InvoiceQbStatus;
use App\Enum\SyncDirection;
use App\Models\Invoice;
use App\Services\Quickbooks\QuickbooksService;

class CheckInvoice
{
    /** @var array<string, mixed> */
    private array $context = [];

    public function __construct(
        private QuickbooksService $service,
        private InvoiceCollisionPolicy $collision,
    ) {
    }

    public function handle(Invoice $invoice): Invoice
    {
        $this->context = [];
        $qb_id = (string) data_get($invoice->sync, 'qb_id', '');

        if ($qb_id === '') {
            return $this->checkUnlinkedInvoice($invoice);
        }

        $qb_record = $this->service->sdk()->findById('Invoice', $qb_id);
        $sync = $invoice->sync ?? new InvoiceSync();
        $previous_status = $sync->status();

        if (!$qb_record) {
            $sync->markSynced($qb_id, $sync->qb_sync_token, false);
            $sync->markPushFailure("QuickBooks invoice {$qb_id} was not found.");
            $invoice->sync = $sync;
            $invoice->saveQuietly();

            $invoice = $invoice->fresh();
            $this->context = $this->buildCheckContext($invoice, null, true, 'not_found');

            return $invoice;
        }

        $sync->markSynced($qb_id, (string) data_get($qb_record, 'SyncToken', ''), false);

        if ($message = $this->collision->linkedInvoiceCheckMessage($invoice, $qb_record)) {
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
        $this->context = $this->buildCheckContext($invoice, $qb_record, true, $outcome);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
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
            $this->context = $this->buildCheckContext($invoice, null, false, InvoiceQbStatus::Syncable->value);

            return $invoice;
        }

        $qb_record = $this->collision->findQbInvoiceByDocNumber((string) $invoice->number, false);

        if ($qb_record) {
            $this->collision->flagNumberCollision($invoice, $qb_record);
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
        $this->context = $this->buildCheckContext($invoice, $qb_record, false, $invoice->sync->status()->value);

        return $invoice;
    }

    /**
     * @return array<string, mixed>
     */
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
                    'matches' => $this->collision->amountsMatch($qb_record, $invoice),
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

    /**
     * @return array<int, string>
     */
    private function recommendedCheckActions(Invoice $invoice, string $outcome, bool $linked): array
    {
        if ($outcome === InvoiceQbStatus::Syncable->value) {
            return !empty($invoice->number)
                && !empty($invoice->sync->qb_status_message)
                && $this->service->syncable('invoice', SyncDirection::PUSH)
                    ? ['force_push']
                    : [];
        }

        if ($outcome === InvoiceQbStatus::DataMismatch->value && $linked) {
            $actions = ['verify_quickbooks_invoice'];

            if ($this->service->syncable('invoice', SyncDirection::PULL)) {
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
}
