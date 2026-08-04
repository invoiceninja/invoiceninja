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
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use App\Services\EDocument\Standards\France\FrancePaymentReceivedNotificationEligibility;
use App\Services\EDocument\Standards\France\FranceSubmissionClaim;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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

        if (! in_array($event->payment_status, [
            TransactionEvent::FR_REPORTING_STATUS_PENDING,
            TransactionEvent::FR_REPORTING_STATUS_FAILED,
        ], true) || data_get($event->payment_request, 'skip_reason')) {
            return;
        }

        $company = Company::query()->with("account")->find($event->company_id);
        $account = $company?->getRelation('account');

        if (! $company
            || $company->is_disabled
            || ! $account instanceof Account
            || $account->is_flagged
            || ! (bool) $company->getSetting('france_reporting_enabled')) {
            return;
        }

        $request = $event->payment_request ?? [];
        $originalDocumentGuid = (string) data_get($request, "original_document_guid", "");

        if ($originalDocumentGuid === "") {
            $this->markFailed($event, ["message" => "Missing original Storecove document submission GUID."]);
            return;
        }

        $sourceDate = (string) data_get($request, 'source_date', '');

        if ($sourceDate !== ''
            && CarbonImmutable::parse($sourceDate, 'Europe/Paris')->startOfDay()
                ->greaterThan(CarbonImmutable::now('Europe/Paris')->startOfDay())) {
            return;
        }

        if (! app(FrancePaymentReceivedNotificationEligibility::class)->isEligible($event)) {
            $this->markSkipped($event, "Payment received notification is no longer eligible.");
            return;
        }

        if (! $this->originalInvoiceIsCleared($event)) {
            $this->markFailed($event, ["message" => "Original Storecove document has not cleared yet."]);
            return;
        }

        $claims = app(FranceSubmissionClaim::class);
        $claimToken = $claims->claim([$event->id]);

        if (! $claimToken) {
            return;
        }

        $claimCompleted = false;

        try {
            $event = TransactionEvent::query()->find($event->id);

            if (! $event || ! $claims->isOwnedBy($event, $claimToken)) {
                return;
            }

            $request = $event->payment_request ?? [];
            $sourceDate = (string) data_get($request, 'source_date', '');

            if ($sourceDate !== ''
                && CarbonImmutable::parse($sourceDate, 'Europe/Paris')->startOfDay()
                    ->greaterThan(CarbonImmutable::now('Europe/Paris')->startOfDay())) {
                return;
            }

            if (! app(FrancePaymentReceivedNotificationEligibility::class)->isEligible($event)) {
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

            $this->recordSubmissionResponse($event, $response, $claimToken);
            $claimCompleted = true;
        } finally {
            if (! $claimCompleted) {
                $claims->release([$event->id ?? $this->transactionEventId], $claimToken);
            }
        }
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
    private function recordSubmissionResponse(TransactionEvent $event, array $response, string $claimToken): void
    {
        $guid = $response["guid"] ?? null;
        $successful = is_string($guid) && $guid !== "";

        DB::transaction(function () use ($event, $guid, $successful, $response, $claimToken): void {
            $claimedEvent = TransactionEvent::query()->lockForUpdate()->find($event->id);

            if (! $claimedEvent || ! app(FranceSubmissionClaim::class)->isOwnedBy($claimedEvent, $claimToken)) {
                throw new \RuntimeException('France payment notification claim was lost before persistence.');
            }

            $claimedEvent->payment_status = $successful
                ? TransactionEvent::FR_REPORTING_STATUS_SUBMITTED
                : TransactionEvent::FR_REPORTING_STATUS_FAILED;

            $request = $claimedEvent->payment_request ?? [];
            unset($request[FranceSubmissionClaim::TOKEN], $request[FranceSubmissionClaim::EXPIRES_AT]);
            $claimedEvent->payment_request = [
                ...$request,
                "guid" => $guid,
                "submitted_at" => $successful ? now()->toIso8601String() : null,
                "error" => $successful ? null : $response,
            ];
            $claimedEvent->save();

            if ($successful) {
                $this->deleteSupersededNotificationEvents($claimedEvent);
            }
        }, attempts: 3);
    }

    /**
     * Remove superseded non-submitted notification rows once a later row has been accepted by Storecove.
     */
    private function deleteSupersededNotificationEvents(TransactionEvent $event): void
    {
        $originalDocumentGuid = (string) data_get($event->payment_request, 'original_document_guid', '');

        TransactionEvent::query()
            ->where("company_id", $event->company_id)
            ->where("invoice_id", $event->invoice_id)
            ->where("event_id", TransactionEvent::FR_B2B_PAYMENT_RECEIVED_NOTIFICATION)
            ->where("payment_request->original_document_guid", $originalDocumentGuid)
            ->where("id", "!=", $event->id)
            ->where("payment_status", "!=", TransactionEvent::FR_REPORTING_STATUS_SUBMITTED)
            ->delete();
    }

    private function originalInvoiceIsCleared(TransactionEvent $event): bool
    {
        $invoice = Invoice::withTrashed()->find($event->invoice_id);

        return $invoice && ($invoice->backup->e_invoice_status === "cleared" || ! is_null($invoice->backup->e_invoice_cleared_at));
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
