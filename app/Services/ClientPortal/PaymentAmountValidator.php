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

namespace App\Services\ClientPortal;

use App\Models\Invoice;
use App\Utils\Number;

final class PaymentAmountValidator
{
    public static function validate(
        Invoice $invoice,
        mixed $amount,
        object $settings,
        int $precision,
    ): ?string {
        $amount = Number::roundValue(Number::parseFloat($amount), $precision);
        $payable = Number::roundValue(
            $invoice->partial > 0
                ? $invoice->partial
                : ($invoice->status_id === Invoice::STATUS_DRAFT
                    ? $invoice->amount
                    : $invoice->balance),
            $precision,
        );

        $minimum = $settings->client_portal_allow_under_payment
            ? min($payable, $settings->client_portal_under_payment_minimum)
            : $payable;

        if ($amount < $minimum) {
            return ctrans('texts.minimum_required_payment', ['amount' => $minimum]);
        }

        if (! $settings->client_portal_allow_over_payment && $amount > $payable) {
            return ctrans('texts.over_payments_disabled');
        }

        return null;
    }
}
