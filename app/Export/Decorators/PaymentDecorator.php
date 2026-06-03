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

namespace App\Export\Decorators;

use App\Models\Invoice;
use App\Models\Payment;

class PaymentDecorator extends Decorator implements DecoratorInterface
{
    private $entity_key = 'payment';

    public function transform(string $key, $entity): mixed
    {
        $payment = false;

        $loaded_payments = null;

        if (! $entity instanceof Payment && is_object($entity) && method_exists($entity, 'relationLoaded') && $entity->relationLoaded('payments')) {
            $loaded_payments = $entity->getRelation('payments');
        }

        if ($entity instanceof Payment) {
            $payment = $entity;
        } elseif ($entity instanceof Invoice && $entity->relationLoaded('current_paymentable')) {
            $paymentable = $entity->getRelation('current_paymentable');
            if ($paymentable && $paymentable->payment) {
                $payment = $paymentable->payment;
                $payment->setRelation('current_paymentable', $paymentable);
            }
        } elseif ($loaded_payments instanceof \Illuminate\Support\Collection && $loaded_payments->isNotEmpty()) {
            $payment = $loaded_payments->first();
        } elseif ($entity->payment) {
            $payment = $entity->payment;
        } elseif ($entity->payments()->exists()) {
            $payment = $entity->payments()->first();
        }

        if ($key == 'amount' && (!$entity instanceof Payment)) {
            if ($loaded_payments instanceof \Illuminate\Support\Collection) {
                $active_payments = $this->activePayments($loaded_payments);

                return $active_payments->isNotEmpty() ? $active_payments->sum(fn (Payment $payment): float => (float) ($payment->pivot->amount ?? 0)) : ctrans('texts.unpaid');
            }

            return $entity->payments()->exists() ? $entity->payments()->withoutTrashed()->sum('paymentables.amount') : ctrans('texts.unpaid');
        } elseif ($key == 'refunded' && (!$entity instanceof Payment)) {
            if ($loaded_payments instanceof \Illuminate\Support\Collection) {
                return $this->activePayments($loaded_payments)->sum(fn (Payment $payment): float => (float) ($payment->pivot->refunded ?? 0));
            }

            return $entity->payments()->exists() ? $entity->payments()->withoutTrashed()->sum('paymentables.refunded') : '';
        } elseif ($key == 'applied' && (!$entity instanceof Payment)) {
            if ($loaded_payments instanceof \Illuminate\Support\Collection) {
                $active_payments = $this->activePayments($loaded_payments);
                $refunded = $active_payments->sum(fn (Payment $payment): float => (float) ($payment->pivot->refunded ?? 0));
                $amount = $active_payments->sum(fn (Payment $payment): float => (float) ($payment->pivot->amount ?? 0));

                return $active_payments->isNotEmpty() ? ($amount - $refunded) : '';
            }

            $refunded = $entity->payments()->withoutTrashed()->sum('paymentables.refunded');
            $amount = $entity->payments()->withoutTrashed()->sum('paymentables.amount');
            return $entity->payments()->withoutTrashed()->exists() ? ($amount - $refunded) : '';
        }

        if ($payment && method_exists($this, $key)) {
            return $this->{$key}($payment);
        } elseif ($payment && ($payment->{$key} ?? false)) {
            return $payment->{$key};
        }

        return '';
    }

    private function activePayments(\Illuminate\Support\Collection $payments): \Illuminate\Support\Collection
    {
        return $payments->reject(fn (Payment $payment): bool => $payment->trashed());
    }

    public function date(Payment $payment)
    {
        return $payment->date ?? '';
    }

    public function applied_date(Payment $payment)
    {
        $paymentable = $payment->relationLoaded('current_paymentable') ? $payment->getRelation('current_paymentable') : null;

        if (! $paymentable) {
            return '';
        }

        $ts = $paymentable->created_at;

        return $ts ? \Carbon\Carbon::createFromTimestamp($ts)->setTimezone($payment->company->timezone()->name)->format('Y-m-d') : '';
    }

    public function applied_amount(Payment $payment)
    {
        $paymentable = $payment->relationLoaded('current_paymentable') ? $payment->getRelation('current_paymentable') : null;

        return $paymentable ? $paymentable->amount : '';
    }

    public function applied_refunded(Payment $payment)
    {
        $paymentable = $payment->relationLoaded('current_paymentable') ? $payment->getRelation('current_paymentable') : null;

        return $paymentable ? $paymentable->refunded : '';
    }

    public function amount(Payment $payment)
    {
        return $payment->amount ?? '';
    }

    public function refunded(Payment $payment)
    {
        return $payment->refunded ?? '';
    }

    public function applied(Payment $payment)
    {
        return $payment->applied ?? '';
    }
    public function transaction_reference(Payment $payment)
    {
        return $payment->transaction_reference ?? '';
    }
    public function currency(Payment $payment)
    {
        return $payment->currency()->exists() ? $payment->currency->code : $payment->company->currency()->code;
    }

    public function exchange_rate(Payment $payment)
    {
        return $payment->exchange_rate ?? 1;
    }

    public function method(Payment $payment)
    {
        return $payment->translatedType();
    }

    public function status(Payment $payment)
    {
        return $payment->stringStatus($payment->status_id);
    }

    public function private_notes(Payment $payment)
    {
        return strip_tags($payment->private_notes ?? '');
    }

    public function user_id(Payment $payment)
    {
        return $payment->user ? $payment->user->present()->name() : '';
    }

    public function assigned_user_id(Payment $payment)
    {
        return $payment->assigned_user ? $payment->assigned_user->present()->name() : '';
    }

    public function project_id(Payment $payment)
    {
        return $payment->project()->exists() ? $payment->project->name : '';
    }

    ///////////////////////////////////////////////////

    public function vendor_id(Payment $payment)
    {
        return $payment->vendor()->exists() ? $payment->vendor->name : '';
    }

    public function exchange_currency(Payment $payment)
    {
        return $payment->exchange_currency()->exists() ? $payment->exchange_currency->code : '';
    }

    public function gateway_type_id(Payment $payment)
    {
        return $payment->gateway_type ? $payment->gateway_type->name : 'Unknown Type';
    }

    public function client_id(Payment $payment)
    {
        return $payment->client->present()->name();
    }

    public function type_id(Payment $payment)
    {
        return $payment->translatedType();
    }

}
