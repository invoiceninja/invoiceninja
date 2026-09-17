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

namespace App\DataMapper\TaxReport;

/**
 * Payment history for tracking partial payments across periods
 */
class PaymentHistory
{
    public int $paymentable_id;
    public string $number;
    public string $date;
    public float $amount;
    public float $refunded;
    public float $exchange_rate;
    public int $currency_id;

    public function __construct(array $attributes = [])
    {
        $this->paymentable_id = (int) ($attributes['paymentable_id'] ?? 0);
        $this->number = $attributes['number'] ?? '';
        $this->date = $attributes['date'] ?? '';
        $this->amount = $attributes['amount'] ?? 0.0;
        $this->refunded = $attributes['refunded'] ?? 0.0;
        $this->exchange_rate = (float) ($attributes['exchange_rate'] ?? 1.0);
        $this->currency_id = (int) ($attributes['currency_id'] ?? 0);
    }

    public function toArray(): array
    {
        return [
            'paymentable_id' => $this->paymentable_id,
            'number' => $this->number,
            'date' => $this->date,
            'amount' => $this->amount,
            'refunded' => $this->refunded,
            'exchange_rate' => $this->exchange_rate,
            'currency_id' => $this->currency_id,
        ];
    }
}
