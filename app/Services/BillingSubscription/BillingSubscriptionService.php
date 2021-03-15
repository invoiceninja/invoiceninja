<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\BillingSubscription;

class BillingSubscriptionService
{

    private $billing_subscription;

    public function __construct(BillingSubscription $billing_subscription)
    {
        $this->billing_subscription = $billing_subscription;
    }

    public function createInvoice($payment_hash)
    {
        //create the invoice if necessary ie. only is a payment was actually processed 
    }

    public function createClientSubscription($payment_hash)
    {
        //create the client sub record
    }

    public function triggerWebhook($payment_hash)
    {
        //hit the webhook to after a successful onboarding
    }

    public function fireNotifications()
    {
        //scan for any notification we are required to send
    }
}
