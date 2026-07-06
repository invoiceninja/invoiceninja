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

namespace App\Jobs\EDocument;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\TransactionEvent;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class SubmitFrancePaymentReceivedNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public $tries = 1;

    public function __construct(
        private int $transactionEventId,
        private string $db,
    ) {}

    public function handle(Storecove $storecove): void
    {
        MultiDB::setDb($this->db);

        $event = TransactionEvent::query()->find($this->transactionEventId);

        if (! $event || $event->event_id !== TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION) {
            return;
        }

        if ($event->payment_status === TransactionEvent::FR_REPORTING_STATUS_SUBMITTED) {
            return;
        }

        $company = Company::query()->with("account")->find($event->company_id);

        if (! $company) {
            return;
        }

        $request = $event->payment_request ?? [];
        $originalDocumentGuid = (string) data_get($request, "original_document_guid", "");

        if ($originalDocumentGuid === "") {
            $this->markFailed($event, ["message" => "Missing original Storecove document submission GUID."]);
            return;
        }

        if (! $this->eventIsStillEligible($event)) {
            $this->markSkipped($event, "Payment received notification is no longer eligible.");
            return;
        }

        if (! $this->originalInvoiceIsCleared($event)) {
            $this->markFailed($event, ["message" => "Original Storecove document has not cleared yet."]);
            return;
        }

        $idempotencyGuid = (string) (data_get($request, "idempotency_guid") ?: Str::uuid()->toString());
        $event->payment_request = [
            ...$request,
            "idempotency_guid" => $idempotencyGuid,
        ];
        $event->save();

        try {
            $response = $storecove->proxy
                ->setCompany($company)
                ->submitDocument($this->payload($company, $originalDocumentGuid, $idempotencyGuid));
        } catch (Throwable $exception) {
            report($exception);
            $this->markFailed($event, ["message" => $exception->getMessage()]);
            return;
        }

        $this->recordSubmissionResponse($event, $response);
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->transactionEventId.$this->db.".fr-payment-received-notification"))
                ->releaseAfter(60)
                ->expireAfter(60),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Company $company, string $originalDocumentGuid, string $idempotencyGuid): array
    {
        return [
            "forDocumentSubmissionGuid" => $originalDocumentGuid,
            "idempotencyGuid" => $idempotencyGuid,
            "document" => [
                "documentType" => "payment_received_notification",
                "paymentReceivedNotification" => [
                    "mode" => "auto",
                ],
            ],
            "tenant_id" => $company->company_key,
            "account_key" => $company->account->key,
            "e_invoicing_token" => $company->account->e_invoicing_token,
        ];
    }

    /**
     * @param array<string, mixed> $response
     */
    private function recordSubmissionResponse(TransactionEvent $event, array $response): void
    {
        $guid = $response["guid"] ?? null;
        $successful = is_string($guid) && $guid !== "";

        $event->payment_status = $successful
            ? TransactionEvent::FR_REPORTING_STATUS_SUBMITTED
            : TransactionEvent::FR_REPORTING_STATUS_FAILED;

        $event->payment_request = [
            ...($event->payment_request ?? []),
            "guid" => $guid,
            "submitted_at" => $successful ? now()->toIso8601String() : null,
            "error" => $successful ? null : $response,
        ];
        $event->save();
        if ($successful) {
            $this->deleteSupersededNotificationEvents($event);
        }
    }

    /**
     * Remove superseded non-submitted notification rows once a later row has been accepted by Storecove.
     */
    private function deleteSupersededNotificationEvents(TransactionEvent $event): void
    {
        TransactionEvent::query()
            ->where("company_id", $event->company_id)
            ->where("invoice_id", $event->invoice_id)
            ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->where("id", "!=", $event->id)
            ->where("payment_status", "!=", TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
            ->delete();
    }

    private function eventIsStillEligible(TransactionEvent $event): bool
    {
        $paymentableId = (int) data_get($event->payment_request, "paymentable_id", 0);
        if ($paymentableId <= 0) {
            return false;
        }
        $payment = Payment::withTrashed()
            ->with(["client.country", "client.company", "company"])
            ->find($event->payment_id);
        $invoice = Invoice::withTrashed()->find($event->invoice_id);
        $paymentable = Paymentable::withTrashed()->find($paymentableId);
        if (! $payment || ! $invoice || ! $paymentable) {
            return false;
        }
        if ((int) $payment->status_id !== Payment::STATUS_COMPLETED || $payment->is_deleted) {
            return false;
        }
        if ($invoice->is_deleted || ! $this->invoiceIsPaidInFull($invoice)) {
            return false;
        }
        if ($paymentable->trashed()
            || (int) $paymentable->payment_id !== (int) $payment->id
            || (int) $paymentable->paymentable_id !== (int) $invoice->id
            || $paymentable->paymentable_type !== "invoices") {
            return false;
        }
        return $this->clientStillRequiresPaymentReceivedNotification($payment);
    }
    private function clientStillRequiresPaymentReceivedNotification(Payment $payment): bool
    {
        if (! $payment->client->relationLoaded("company")) {
            $payment->client->setRelation("company", $payment->company);
        }
        return $payment->client->reportableFrTransaction()
            && ($payment->client->classification ?? "business") !== "individual"
            && $payment->client->country?->iso_3166_2 === "FR";
    }

    private function originalInvoiceIsCleared(TransactionEvent $event): bool
    {
        $invoice = Invoice::withTrashed()->find($event->invoice_id);

        return $invoice && ($invoice->backup->e_invoice_status === "cleared" || ! is_null($invoice->backup->e_invoice_cleared_at));
    }

    private function invoiceIsPaidInFull(Invoice $invoice): bool
    {
        return (int) $invoice->status_id === Invoice::STATUS_PAID
            || (float) ($invoice->balance ?? 0) <= 0.0;
    }
    private function markSkipped(TransactionEvent $event, string $reason): void
    {
        $event->payment_status = TransactionEvent::FR_REPORTING_STATUS_FAILED;
        $event->payment_request = [
            ...($event->payment_request ?? []),
            "error" => ["message" => $reason],
            "skip_reason" => $reason,
            "skipped_at" => now()->toIso8601String(),
        ];
        $event->save();
    }
    /**
     * @param array<string, mixed> $error
     */
    private function markFailed(TransactionEvent $event, array $error): void
    {
        $event->payment_status = TransactionEvent::FR_REPORTING_STATUS_FAILED;
        $event->payment_request = [
            ...($event->payment_request ?? []),
            "error" => $error,
        ];
        $event->save();
    }
}

