<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Payment;

use App\Models\Company;
use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class PaymentWasEmailed.
 */
class PaymentWasEmailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @var Payment
     */
    public $payment;

    public $company;

    public $event_vars;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Payment $payment, Company $company, array $event_vars)
    {
        $this->payment = $payment;
        $this->company = $company;
        $this->event_vars = $event_vars;
    }
}
