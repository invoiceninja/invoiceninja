<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Queue\SerializesModels;

/**
 * Class PaymentWasRestored.
 */
class PaymentWasRestored extends Event
{
    use SerializesModels;

    /**
     * @var Payment
     */
    public $payment;
    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     * @param $fromDeleted
     */
    public function __construct(Payment $payment, $fromDeleted)
    {
        $this->payment = $payment;
        $this->fromDeleted = $fromDeleted;
    }
}
