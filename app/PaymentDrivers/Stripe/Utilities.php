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

namespace App\PaymentDrivers\Stripe;

use App\Models\Payment;

trait Utilities
{
    /**
     * Resolve a Payment by either the Stripe Charge ID (ch_ / py_) or the
     * PaymentIntent ID (pi_).
     *
     * Several Stripe drivers (ACH, ACSS, BankTransfer, SEPA, BACS, BECS, iDEAL,
     * SOFORT, Bancontact, GIROPAY, EPS, Przelewy24, Klarna, FPX, Alipay) write
     * the PaymentIntent ID into Payment.transaction_reference rather than the
     * Charge ID. For async-settling bank methods this is unavoidable since no
     * Charge exists yet at write time. Webhooks like charge.refunded carry the
     * Charge ID, so a single-key lookup misses these payments.
     *
     * @param  array<string, mixed>  $source  Stripe webhook `data.object` payload
     */
    public static function findPaymentByStripeReference(int $companyId, array $source, bool $withTrashed = false): ?Payment
    {
        $candidates = [];

        $id = $source['id'] ?? null;
        $paymentIntentId = $source['payment_intent'] ?? null;

        if (is_string($id) && str_starts_with($id, 'pi_')) {
            $paymentIntentId = $paymentIntentId ?? $id;
            $id = null;
        }

        if (is_string($id)) {
            $candidates[] = $id;
        }

        if (is_string($paymentIntentId)) {
            $candidates[] = $paymentIntentId;
        }

        $latestCharge = $source['latest_charge'] ?? null;
        if (is_string($latestCharge)) {
            $candidates[] = $latestCharge;
        }

        $nestedChargeId = $source['charges']['data'][0]['id'] ?? null;
        if (is_string($nestedChargeId)) {
            $candidates[] = $nestedChargeId;
        }

        $candidates = array_values(array_unique(array_filter($candidates, fn ($v) => is_string($v) && $v !== ''))); // @phpstan-ignore-line

        if (empty($candidates)) {
            return null;
        }

        $query = Payment::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query
            ->where('company_id', $companyId)
            ->whereIn('transaction_reference', $candidates)
            ->first();
    }

    /*Helpers for currency conversions, NOTE* for some currencies we need to change behaviour */
    public function convertFromStripeAmount($amount, $precision, $currency)
    {
        if (in_array($currency->code, ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'])) {
            return $amount;
        }

        return $amount / pow(10, $precision);
    }

    public function convertToStripeAmount($amount, $precision, $currency)
    {
        if (in_array($currency->code, ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'])) {
            return $amount;
        }

        return round(($amount * pow(10, $precision)), 0);
    }
}
