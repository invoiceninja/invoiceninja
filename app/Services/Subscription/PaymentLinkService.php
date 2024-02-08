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

namespace App\Services\Subscription;

use App\Models\PaymentHash;
use App\Models\Subscription;
use App\Models\ClientContact;
use App\Models\RecurringInvoice;

class PaymentLinkService
{

    public const WHITE_LABEL = 4316;

    public function __construct(public Subscription $subscription)
    {
    }
    
    /**
     * CompletePurchase
     *
     * Perform the initial purchase of a one time 
     * or recurring product
     * 
     * @param  PaymentHash $payment_hash
     * @return Illuminate\Routing\Redirector
     */
    public function completePurchase(PaymentHash $payment_hash): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {

    }

    /**
     * isEligible
     * ["message" => "Success", "status_code" => 200];
     * @param  ClientContact $contact
     * @return array{"message": string, "status_code": int}
     */
    public function isEligible(ClientContact $contact): array
    {
        
    }

    /* Starts the process to create a trial
        - we create a recurring invoice, which has its next_send_date as now() + trial_duration
        - we then hit the client API end point to advise the trial payload
        - we then return the user to either a predefined user endpoint, OR we return the user to the recurring invoice page.

     * startTrial
     *
     * @param  array $data{contact_id: int, client_id: int, bundle: \Illuminate\Support\Collection, coupon?: string, }
     * @return Illuminate\Routing\Redirector
     */
    public function startTrial(array $data): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {

    }
    
    /**
     * calculateUpdatePriceV2
     *
     * Need to change the naming of the method
     * 
     * @param  RecurringInvoice $recurring_invoice - The Current Recurring Invoice for the subscription.
     * @param  Subscription $target - The new target subscription to move to
     * @return float - the upgrade price
     */
    public function calculateUpgradePriceV2(RecurringInvoice $recurring_invoice, Subscription $target): ?float
    {
       return (new UpgradePrice($recurring_invoice, $target))->run();
    }
}