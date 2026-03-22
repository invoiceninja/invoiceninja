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
use App\Models\Payment;
use App\DataMapper\PaymentSync;
use App\Interfaces\SyncInterface;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\PaymentTransformer;

class QbPayment implements SyncInterface
{
    public function __construct(public QuickbooksService $service) {}

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Payment', $id);
    }

    public function importToNinja(array $records): void
    {
        foreach ($records as $payment) {
            $payment_transformer = new PaymentTransformer($this->service->company);

            $transformed = $payment_transformer->qbToNinja($payment);

            $ninja_payment = $payment_transformer->buildPayment($payment);
            $ninja_payment->service()->applyNumber()->save();

            $payment_transformer->associatePaymentToInvoice($ninja_payment, $payment);
        }
    }

    public function syncToNinja(array $records): void
    {
        $transformer = new PaymentTransformer($this->service->company);

        foreach ($records as $record) {
            $ninja_data = $transformer->qbToNinja($record);
        }
    }

    /**
     * Push payments from Invoice Ninja to QuickBooks.
     *
     * Handles create, update, and void operations.
     *
     * @param array $records
     * @return void
     */
    public function syncToForeign(array $records): void
    {
        $transformer = new PaymentTransformer($this->service->company);

        foreach ($records as $payment) {
            if (!$payment instanceof Payment) {
                continue;
            }

            // Skip immutable payments (QB closed period)
            if ($payment->sync && $payment->sync->qb_immutable) {
                nlog("QuickBooks: Skipping immutable payment {$payment->id}");
                continue;
            }

            // Handle deletion/cancellation → void in QB
            if ($payment->is_deleted || $payment->status_id === Payment::STATUS_CANCELLED) {
                $this->voidInQuickbooks($payment);
                continue;
            }

            // Only sync completed or partially-refunded payments
            if ($payment->status_id !== Payment::STATUS_COMPLETED
                && $payment->status_id !== Payment::STATUS_PARTIALLY_REFUNDED) {
                continue;
            }

            // Skip payments with no invoices and zero/negative amount (pure credit-to-credit)
            if ($payment->amount <= 0 && $payment->invoices()->count() === 0) {
                continue;
            }

            try {
                // Refresh from DB to get latest qb_id — prevents duplicate
                // creates if a prior job already pushed this payment to QB
                $payment->refresh();

                $qb_payment_data = $transformer->ninjaToQb($payment, $this->service);

                if (!empty($payment->sync->qb_id)) {
                    // UPDATE path: fetch current SyncToken
                    $existing = $this->find($payment->sync->qb_id);
                    if ($existing) {
                        $qb_payment_data['SyncToken'] = $existing->SyncToken ?? '0';
                    }

                    $qb_payment = \QuickBooksOnline\API\Facades\Payment::create($qb_payment_data);
                    $result = $this->service->sdk->Update($qb_payment);

                    // Update sync metadata
                    $sync = $payment->sync ?? new PaymentSync();
                    $sync->qb_sync_token = data_get($result, 'SyncToken') ?? '';
                    $sync->last_synced_at = now()->toIso8601String();
                    $payment->sync = $sync;
                    $payment->saveQuietly();

                    nlog("QuickBooks: Updated payment {$payment->id} (QB ID: {$payment->sync->qb_id})");
                } else {
                    // CREATE path
                    $qb_payment = \QuickBooksOnline\API\Facades\Payment::create($qb_payment_data);
                    $result = $this->service->sdk->Add($qb_payment);

                    $sync = $payment->sync ?? new PaymentSync();
                    $sync->qb_id = data_get($result, 'Id') ?? data_get($result, 'Id.value') ?? '';
                    $sync->qb_sync_token = data_get($result, 'SyncToken') ?? '';
                    $sync->last_synced_at = now()->toIso8601String();
                    $payment->sync = $sync;
                    $payment->saveQuietly();

                    nlog("QuickBooks: Created payment {$payment->id} (QB ID: {$sync->qb_id})");
                }
            } catch (\Exception $e) {
                nlog("QuickBooks: Error pushing payment {$payment->id}: {$e->getMessage()}");
                throw $e;
            }
        }
    }

    /**
     * Void a payment in QuickBooks.
     *
     * Handles closed-period errors gracefully by flagging the payment
     * as immutable rather than retrying.
     *
     * @param Payment $payment
     * @return void
     */
    private function voidInQuickbooks(Payment $payment): void
    {
        if (!$payment->sync || empty($payment->sync->qb_id)) {
            return;
        }

        try {
            $qb_payment = $this->find($payment->sync->qb_id);

            if (!$qb_payment) {
                nlog("QuickBooks: Payment QB ID {$payment->sync->qb_id} not found in QB, skipping void");
                return;
            }

            // Already voided in QB
            if (data_get($qb_payment, 'TxnStatus') === 'Voided') {
                nlog("QuickBooks: Payment QB ID {$payment->sync->qb_id} already voided");
                $sync = $payment->sync;
                $sync->qb_id = '';
                $sync->qb_sync_token = '';
                $payment->sync = $sync;
                $payment->saveQuietly();
                return;
            }

            // Attempt void
            $this->service->sdk->Void($qb_payment);

            // Success: clear QB tracking
            $sync = $payment->sync;
            $sync->qb_id = '';
            $sync->qb_sync_token = '';
            $sync->last_synced_at = now()->toIso8601String();
            $payment->sync = $sync;
            $payment->saveQuietly();

            nlog("QuickBooks: Voided payment {$payment->id}");

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            if ($this->isClosedPeriodError($errorMessage)) {
                $sync = $payment->sync;
                $sync->qb_immutable = true;
                $sync->qb_void_failed = true;
                $sync->qb_void_error = $this->extractReadableError($errorMessage);
                $payment->sync = $sync;
                $payment->saveQuietly();

                nlog("QuickBooks: Payment {$payment->id} void failed (closed period): {$errorMessage}");

                $number = $payment->number ?? $payment->id;
                $note = "QuickBooks: Payment #{$number} could not be voided: {$sync->qb_void_error}. The accounting period may be closed. Please void this payment directly in QuickBooks or re-open the period.";
                $payment->private_notes = ltrim(($payment->private_notes ?? '') . "\n" . $note);
                $payment->saveQuietly();

                // Do NOT re-throw — prevents retry loops for permanent failures
            } else {
                nlog("QuickBooks: Error voiding payment {$payment->id}: {$errorMessage}");
                throw $e;
            }
        }
    }

    /**
     * Handle QB webhook update/create events (pull direction).
     *
     * @param string $id QB Payment ID
     * @param string $last_updated ISO8601 timestamp from QB webhook
     * @return void
     */
    public function sync(string $id, string $last_updated): void
    {
        $qb_record = $this->find($id);

        if (!$this->service->syncable('payment', \App\Enum\SyncDirection::PULL)) {
            return;
        }

        // Voided in QB → delete in Ninja
        if (data_get($qb_record, 'TxnStatus') === 'Voided') {
            $this->delete($id);
            return;
        }

        // Find existing IN payment by QB ID
        $payment = Payment::query()
            ->withTrashed()
            ->where('company_id', $this->service->company->id)
            ->where('sync->qb_id', $id)
            ->first();

        if (!$payment) {
            // New payment from QB — import it
            QuickbooksService::$importing[$this->service->company->id] = true;
            try {
                $this->importToNinja([$qb_record]);
            } finally {
                unset(QuickbooksService::$importing[$this->service->company->id]);
            }
            return;
        }

        // Existing payment — update if QB is newer
        if (Carbon::parse($last_updated)->gt(Carbon::parse($payment->updated_at))) {
            QuickbooksService::$importing[$this->service->company->id] = true;
            try {
                $transformer = new PaymentTransformer($this->service->company);
                $ninja_data = $transformer->qbToNinja($qb_record);
                $payment->fill($ninja_data);
                $payment->saveQuietly();
            } finally {
                unset(QuickbooksService::$importing[$this->service->company->id]);
            }
        }
    }

    /**
     * Handle QB webhook delete/void events (pull direction).
     *
     * @param string $id QB Payment ID
     * @return void
     */
    public function delete(string $id): void
    {
        if (!$this->service->syncable('payment', \App\Enum\SyncDirection::PULL)) {
            return;
        }

        $payment = Payment::query()
            ->withTrashed()
            ->where('company_id', $this->service->company->id)
            ->where('sync->qb_id', $id)
            ->first();

        if ($payment && !$payment->is_deleted) {
            QuickbooksService::$importing[$this->service->company->id] = true;
            try {
                $payment->sync = null;
                $payment->saveQuietly();
                (new \App\Services\Payment\DeletePaymentV2($payment, true))->run();
            } finally {
                unset(QuickbooksService::$importing[$this->service->company->id]);
            }
        }
    }

    /**
     * Detect if a QB error is due to a closed accounting period.
     *
     * @param string $errorMessage
     * @return bool
     */
    private function isClosedPeriodError(string $errorMessage): bool
    {
        $patterns = [
            'closed period',
            'period has been closed',
            'accounting period',
            'you can\'t void',
            'business validation error',
            'error 6140',
            'error 6210',
        ];

        $lower = strtolower($errorMessage);

        foreach ($patterns as $pattern) {
            if (str_contains($lower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract a human-readable error from the SDK's raw exception message.
     *
     * @param string $rawMessage
     * @return string
     */
    private function extractReadableError(string $rawMessage): string
    {
        if (preg_match('/with body:\s*\[(.+)\]/s', $rawMessage, $matches)) {
            $body = trim($matches[1]);

            try {
                $xml = @simplexml_load_string($body);
                if ($xml !== false && isset($xml->Fault->Error)) {
                    $error = $xml->Fault->Error;
                    $message = (string) ($error->Message ?? '');
                    $detail = (string) ($error->Detail ?? '');

                    if ($message && $detail) {
                        return "{$message} - {$detail}";
                    }

                    return $message ?: $detail;
                }
            } catch (\Throwable $e) {
                // XML parsing failed, fall through
            }
        }

        $cleaned = str_replace('Request is not made successful. ', '', $rawMessage);

        return mb_substr($cleaned, 0, 500);
    }

}
