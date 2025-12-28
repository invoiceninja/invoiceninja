<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper\TaxReport;

/**
 * Payment history for tracking partial payments across periods
 */
class PaymentHistory
{
    public string $number;
    public string $date;
    public float $amount;
    public float $refunded;

    public function __construct(array $attributes = [])
    {
        $this->number = $attributes['number'] ?? '';
        $this->date = $attributes['date'] ?? '';
        $this->amount = $attributes['amount'] ?? 0.0;
        $this->refunded = $attributes['refunded'] ?? 0.0;
    }

    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'date' => $this->date,
            'amount' => $this->amount,
            'refunded' => $this->refunded,
        ];
    }
}
