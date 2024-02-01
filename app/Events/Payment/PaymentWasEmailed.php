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

use App\Models\ClientContact;
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
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Payment $payment
     * @param Company $company
     * @param ClientContact $contact
     * @param array $event_vars
     */
    public function __construct(public Payment $payment, public Company $company, public ClientContact $contact, public array $event_vars)
    {
    }
}
