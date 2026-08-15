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

namespace App\Services\EDocument\Standards\France;

use App\Jobs\EDocument\RecordFranceEReportingPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Utils\BcMath;

class FrancePaymentApplicationRecorder
{
    public const MOVEMENT_APPLIED = 'applied';

    public const MOVEMENT_CREDIT_APPLIED = 'credit_applied';

    public const MOVEMENT_REFUNDED = 'refunded';

    public const MOVEMENT_DELETED = 'deleted';

    public const MOVEMENT_DATE_REVERSED = 'application_date_reversed';

    public const MOVEMENT_DATE_REAPPLIED = 'application_date_reapplied';

    public function recordStatusTransition(Payment $payment, int $originalStatus): void
    {
        if (! $this->reportingEnabled($payment)) {
            return;
        }

        $movementType = match (true) {
            (int) $payment->status_id === Payment::STATUS_COMPLETED
                && $originalStatus !== Payment::STATUS_COMPLETED
                    => self::MOVEMENT_APPLIED,
            (int) $payment->status_id === Payment::STATUS_FAILED
                && in_array($originalStatus, [
                    Payment::STATUS_COMPLETED,
                    Payment::STATUS_PARTIALLY_REFUNDED,
                    Payment::STATUS_REFUNDED,
                ], true)
                    => self::MOVEMENT_DELETED,
            default => null,
        };

        if (! $movementType) {
            return;
        }

        $payment->loadMissing(['company', 'client.country', 'client.company']);

        $paymentables = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_type', 'invoices')
            ->get();
        $invoices = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->whereIn('id', $paymentables->pluck('paymentable_id'))
            ->get()
            ->keyBy('id');

        foreach ($paymentables as $paymentable) {
            $invoice = $invoices->get($paymentable->paymentable_id);
            $netAmount = BcMath::sub($paymentable->amount, $paymentable->refunded ?? 0, 2);

            if (! $invoice || BcMath::isZero($netAmount, 2)) {
                continue;
            }

            $this->recordMovement(
                payment: $payment,
                invoice: $invoice,
                paymentable: $paymentable,
                movementAmount: $movementType === self::MOVEMENT_DELETED
                    ? BcMath::mul($netAmount, '-1', 2)
                    : $netAmount,
                movementDate: now($payment->company->timezone()?->name ?: config('app.timezone'))->toDateString(),
                movementType: $movementType,
                movementIdentity: $movementType === self::MOVEMENT_DELETED
                    ? implode(':', ['status-failed', $paymentable->id, (string) $payment->updated_at])
                    : null,
            );
        }
    }

    public function recordMovement(
        Payment $payment,
        Invoice $invoice,
        ?Paymentable $paymentable,
        int|float|string $movementAmount,
        ?string $movementDate = null,
        string $movementType = self::MOVEMENT_APPLIED,
        ?string $movementIdentity = null,
    ): void {
        if (! $this->reportingEnabled($payment)) {
            return;
        }

        if (! $this->shouldRecord($payment, $invoice, $movementAmount, $movementType)) {
            return;
        }

        $sourceDate = $this->resolveMovementDate($payment, $paymentable, $movementDate, $movementType);

        if (! $sourceDate) {
            return;
        }

        RecordFranceEReportingPayment::dispatchSync(
            $payment->id,
            $payment->company->db,
            $invoice->id,
            $paymentable?->id,
            (string) $movementAmount,
            $sourceDate,
            $movementType,
            $movementIdentity ?? $this->movementIdentity($payment, $paymentable, $movementType),
            $this->reportingPath($invoice),
            trim((string) ($invoice->backup->guid ?? '')),
        );
    }

    public function recordApplicationDateChange(
        Payment $payment,
        Paymentable $paymentable,
        string $oldDate,
        string $newDate,
        string $mutationIdentity,
    ): void {
        if (! $this->reportingEnabled($payment)) {
            return;
        }

        $invoice = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->where('company_id', $payment->company_id)
            ->find($paymentable->paymentable_id);

        if (! $invoice) {
            return;
        }

        $identity = implode(':', [
            'date-change',
            $paymentable->id,
            $oldDate,
            $newDate,
            $mutationIdentity,
        ]);
        $netAppliedAmount = BcMath::sub(
            $paymentable->amount,
            $paymentable->refunded ?? 0,
            2,
        );

        $this->recordMovement(
            payment: $payment,
            invoice: $invoice,
            paymentable: $paymentable,
            movementAmount: BcMath::mul($netAppliedAmount, '-1', 2),
            movementDate: $oldDate,
            movementType: self::MOVEMENT_DATE_REVERSED,
            movementIdentity: $identity . ':reverse',
        );
        $this->recordMovement(
            payment: $payment,
            invoice: $invoice,
            paymentable: $paymentable,
            movementAmount: $netAppliedAmount,
            movementDate: $newDate,
            movementType: self::MOVEMENT_DATE_REAPPLIED,
            movementIdentity: $identity . ':reapply',
        );
    }

    private function shouldRecord(Payment $payment, Invoice $invoice, int|float|string $movementAmount, string $movementType): bool
    {
        $client = $invoice->getRelationValue('client');
        $company = $payment->getRelationValue('company');

        if (! $client instanceof Client || ! $company instanceof Company) {
            return false;
        }

        if (! $client->relationLoaded('company')) {
            $client->setRelation('company', $company);
        }

        if ($this->isZero($movementAmount)) {
            return false;
        }

        if ($payment->is_deleted && $movementType !== self::MOVEMENT_DELETED) {
            return false;
        }

        if ($invoice->is_deleted && ! in_array($movementType, [self::MOVEMENT_REFUNDED, self::MOVEMENT_DELETED], true)) {
            return false;
        }

        return $this->paymentStatusIsRecordable($payment, $movementType);
    }

    private function reportingEnabled(Payment $payment): bool
    {
        $company = $payment->getRelationValue('company');

        return $company instanceof Company
            && (bool) $company->getSetting('france_reporting_enabled');
    }

    private function paymentStatusIsRecordable(Payment $payment, string $movementType): bool
    {
        return match ($movementType) {
            self::MOVEMENT_REFUNDED => in_array($payment->status_id, [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
            ], true),
            self::MOVEMENT_DELETED => in_array($payment->status_id, [
                Payment::STATUS_COMPLETED,
                Payment::STATUS_PARTIALLY_REFUNDED,
                Payment::STATUS_REFUNDED,
                Payment::STATUS_CANCELLED,
                Payment::STATUS_FAILED,
            ], true),
            default => (int) $payment->status_id === Payment::STATUS_COMPLETED,
        };
    }

    private function resolveMovementDate(Payment $payment, ?Paymentable $paymentable, ?string $movementDate, string $movementType): ?string
    {
        $paymentableDate = $this->paymentableDate($paymentable, $payment);

        if ($this->movementTypeUsesPaymentableDate($movementType)) {
            return $paymentableDate;
        }

        if (! is_null($movementDate) && trim($movementDate) !== '') {
            return $movementDate;
        }

        return $paymentableDate;
    }

    private function movementTypeUsesPaymentableDate(string $movementType): bool
    {
        return in_array($movementType, [self::MOVEMENT_APPLIED, self::MOVEMENT_CREDIT_APPLIED], true);
    }

    private function movementIdentity(Payment $payment, ?Paymentable $paymentable, string $movementType): ?string
    {
        return match ($movementType) {
            self::MOVEMENT_APPLIED,
            self::MOVEMENT_CREDIT_APPLIED => implode(':', [
                $movementType,
                (string) ($paymentable->id ?? 0),
                BcMath::add($payment->applied ?? 0, $paymentable->amount ?? 0, 2),
            ]),
            self::MOVEMENT_REFUNDED,
            self::MOVEMENT_DELETED => $movementType . ':' . (string) ($paymentable->refunded ?? $payment->refunded),
            default => null,
        };
    }

    private function reportingPath(Invoice $invoice): string
    {
        if (($invoice->client->classification ?? 'business') === 'individual') {
            return 'f10';
        }

        return $invoice->client->country?->iso_3166_2 === 'FR'
            ? 'payment_received_notification'
            : 'f10';
    }

    private function paymentableDate(?Paymentable $paymentable, Payment $payment): ?string
    {
        $timezone = $payment->company->timezone()?->name ?: config('app.timezone');

        return app(FrancePaymentApplicationDateResolver::class)->resolve($paymentable, $timezone);
    }

    private function isZero(int|float|string $amount): bool
    {
        return round((float) $amount, 2) == 0.0;
    }
}
