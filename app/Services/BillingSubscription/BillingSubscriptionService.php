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

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Models\BillingSubscription;
use App\Models\ClientSubscription;
use App\Models\PaymentHash;
use App\Models\Product;
use App\Repositories\InvoiceRepository;

class BillingSubscriptionService
{
    /** @var BillingSubscription */
    private $billing_subscription;

    public function __construct(BillingSubscription $billing_subscription)
    {
        $this->billing_subscription = $billing_subscription;
    }

    public function completePurchase(PaymentHash $payment_hash)
    {

        if (!property_exists($payment_hash, 'billing_context')) {
            return;
        }

        // At this point we have some state carried from the billing page
        // to this, available as $payment_hash->data->billing_context. Make something awesome ⭐
        
        // create client subscription record 
        //
        // create recurring invoice if is_recurring
        // 


    }

    public function startTrial(array $data)
    {

    }

    public function createInvoice($data): ?\App\Models\Invoice
    {
        $invoice_repo = new InvoiceRepository();

        $data['line_items'] = $this->createLineItems($data);

        /*
        If trial_enabled -> return early

            -- what we need to know that we don't already
            -- Has a promo code been entered, and does it match
            -- Is this a recurring subscription
            --

            1. Is this a recurring product?
            2. What is the quantity? ie is this a multi seat product ( does this mean we need this value stored in the client sub?)
        */

        return $invoice_repo->save($data, InvoiceFactory::create($this->billing_subscription->company_id, $this->billing_subscription->user_id));

    }

    private function createLineItems($data): array
    {
        $line_items = [];

        $product = $this->billing_subscription->product;

        $item = new InvoiceItem;
        $item->quantity = $data['quantity'];
        $item->product_key = $product->product_key;
        $item->notes = $product->notes;
        $item->cost = $product->price;
        $item->tax_rate1 = $product->tax_rate1 ?: 0;
        $item->tax_name1 = $product->tax_name1 ?: '';
        $item->tax_rate2 = $product->tax_rate2 ?: 0;
        $item->tax_name2 = $product->tax_name2 ?: '';
        $item->tax_rate3 = $product->tax_rate3 ?: 0;
        $item->tax_name3 = $product->tax_name3 ?: '';
        $item->custom_value1 = $product->custom_value1 ?: '';
        $item->custom_value2 = $product->custom_value2 ?: '';
        $item->custom_value3 = $product->custom_value3 ?: '';
        $item->custom_value4 = $product->custom_value4 ?: '';

        //$item->type_id need to switch whether the subscription is a service or product

        $line_items[] = $item;


        //do we have a promocode? enter this as a line item.
        if(strlen($data['coupon']) >=1) 
            $line_items = $this->createPromoLine($data);

        return $line_items;
    }

    private function createPromoLine($data)
    {
        $item = new InvoiceItem;
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
