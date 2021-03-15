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

use App\Models\ClientSubscription;

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
        
        /*
        
        If trial_enabled -> return early

            -- what we need to know that we don't already
            -- Has a promo code been entered, and does it match
            -- Is this a recurring subscription
            -- 

            1. Is this a recurring product?
            2. What is the quantity? ie is this a multi seat product ( does this mean we need this value stored in the client sub?)
        */
    }

    private function convertInvoiceToRecurring()
    {
        //The first invoice is a plain invoice - the second is fired on the recurring schedule.
    }

    public function createClientSubscription($payment_hash, $recurring_invoice_id = null)
    {
        //create the client sub record
        
        //?trial enabled?
        $cs = new ClientSubscription();
        $cs->subscription_id = $this->billing_subscription->id;
        $cs->company_id = $this->billing_subscription->company_id;

        // client_id
        $cs->save();
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
