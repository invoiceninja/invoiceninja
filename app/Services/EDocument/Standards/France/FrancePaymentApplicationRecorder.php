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
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use Throwable;

class FrancePaymentApplicationRecorder
{
    public const MOVEMENT_APPLIED = 'applied';

    public const MOVEMENT_CREDIT_APPLIED = 'credit_applied';

    public const MOVEMENT_REFUNDED = 'refunded';

    public const MOVEMENT_DELETED = 'deleted';

    public function record(Payment $payment, Invoice $invoice): void
    {
        try {
            $paymentable = $this->paymentable($payment, $invoice);
            $movementAmount = $paymentable->amount
                ?? data_get($invoice, 'pivot.amount', $payment->applied ?: $payment->amount ?: 0);
            $movementDate = $this->paymentableDate($paymentable, $payment);

            if (! $movementDate) {
                return;
            }

            $this->recordMovement(
                payment: $payment,
                invoice: $invoice,
                paymentable: $paymentable,
                movementAmount: $movementAmount,
                movementDate: $movementDate,
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function recordMovement(
        Payment $payment,
        Invoice $invoice,
        ?Paymentable $paymentable,
        int|float|string $movementAmount,
        ?string $movementDate = null,
        string $movementType = self::MOVEMENT_APPLIED,
    ): void {
        try {
            if (! $this->shouldRecord($payment, $invoice, $movementAmount, $movementType)) {
                return;
            }

            $sourceDate = $this->resolveMovementDate($payment, $paymentable, $movementDate, $movementType);

            if (! $sourceDate) {
                return;
            }

            RecordFranceEReportingPayment::dispatch(
                $payment->id,
                $payment->company->db,
                $invoice->id,
                $paymentable?->id,
                (string) $movementAmount,
                $sourceDate,
                $movementType,
                $this->movementIdentity($payment, $paymentable, $movementType),
            )->afterCommit();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function shouldRecord(Payment $payment, Invoice $invoice, int|float|string $movementAmount, string $movementType): bool
    {
        if (! $invoice->client->relationLoaded('company')) {
            $invoice->client->setRelation('company', $payment->company);
        }

        if (! $invoice->client->reportableFrTransaction()) {
            return false;
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
            ], true),
            default => (int) $payment->status_id === Payment::STATUS_COMPLETED,
        };
    }

    private function paymentable(Payment $payment, Invoice $invoice): ?Paymentable
    {

        return Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->where('paymentable_id', $invoice->id)
            ->where('paymentable_type', 'invoices')
            ->latest('id')
            ->first();
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
        if (! in_array($movementType, [self::MOVEMENT_REFUNDED, self::MOVEMENT_DELETED], true)) {
            return null;
        }

        return $movementType.':'.(string) ($paymentable?->refunded ?? $payment->refunded ?? 0);
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
