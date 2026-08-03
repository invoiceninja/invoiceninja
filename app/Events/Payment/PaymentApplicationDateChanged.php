<?php

namespace App\Events\Payment;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentApplicationDateChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param array<int, int> $paymentable_ids
     */
    public function __construct(
        public int $payment_id,
        public string $db,
        public string $old_date,
        public string $new_date,
        public array $paymentable_ids,
    ) {}
}
