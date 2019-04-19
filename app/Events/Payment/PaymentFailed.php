<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Queue\SerializesModels;

/**
 * Class PaymentFailed.
 */
class PaymentFailed extends Event
{
    use SerializesModels;

    /**
     * @var Payment
     */
    public $payment;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }
}
