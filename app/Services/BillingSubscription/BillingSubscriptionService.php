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
use App\Jobs\Util\SystemLogger;
use App\Models\BillingSubscription;
use App\Models\ClientSubscription;
use App\Models\PaymentHash;
use App\Models\Product;
use App\Models\SystemLog;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\MakesHash;
use GuzzleHttp\RequestOptions;

class BillingSubscriptionService
{
    use MakesHash;

    /** @var BillingSubscription */
    private $billing_subscription;

    private $client_subscription;

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

    /**
     * Creates the required line items for the invoice 
     * for the billing subscription.
     */
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
        if(strlen($data['coupon']) >=1 && ($data['coupon'] == $this->billing_subscription->promo_code) && $this->billing_subscription->promo_discount > 0) 
            $line_items[] = $this->createPromoLine($data);

        return $line_items;
    }

    /**
     * If a coupon is entered (and is valid)
     * then we apply the coupon discount with a line item.
     */
    private function createPromoLine($data)
    {
        
        $product = $this->billing_subscription->product;
        $discounted_amount = 0;
        $discount = 0;
        $amount = $data['quantity'] * $product->cost;

        if ($this->billing_subscription->is_amount_discount == true) {
            $discount = $this->billing_subscription->promo_discount;
        }
        else {
            $discount = round($amount * ($this->billing_subscription->promo_discount / 100), 2);
        }

        $discounted_amount = $amount - $discount;
        
        $item = new InvoiceItem;
        $item->quantity = 1;
        $item->product_key = ctrans('texts.promo_code');
        $item->notes = ctrans('texts.promo_code');
        $item->cost = $discounted_amount;
        $item->tax_rate1 = $product->tax_rate1 ?: 0;
        $item->tax_name1 = $product->tax_name1 ?: '';
        $item->tax_rate2 = $product->tax_rate2 ?: 0;
        $item->tax_name2 = $product->tax_name2 ?: '';
        $item->tax_rate3 = $product->tax_rate3 ?: 0;
        $item->tax_name3 = $product->tax_name3 ?: '';

        return $item;

    }

    private function convertInvoiceToRecurring($payment_hash)
    {
        //The first invoice is a plain invoice - the second is fired on the recurring schedule.
    }

    public function createClientSubscription($payment_hash)
    {
        //create the client subscription record

        //are we in a trial phase?
        //has money been paid?
        //is this a recurring or one off subscription.

        $cs = new ClientSubscription();
        $cs->subscription_id = $this->billing_subscription->id;
        $cs->company_id = $this->billing_subscription->company_id;

            //if is_trial
            //$cs->trial_started = time();
            //$cs->trial_duration = time() + duration period in seconds

            //trials will not have any monies paid.

            //if a payment has been made
            //$cs->invoice_id = xx

            //if is_recurring
            //create recurring invoice from invoice
            $recurring_invoice = $this->convertInvoiceToRecurring($payment_hash);
            $recurring_invoice->frequency_id = $this->billing_subscription->frequency_id;
            $recurring_invoice->next_send_date = $recurring_invoice->nextDateByFrequency(now()->format('Y-m-d'));
            //$cs->recurring_invoice_id = $recurring_invoice->id;

            //?set the recurring invoice as active - set the date here also based on the frequency?

            //$cs->quantity = xx

            // client_id
            //$cs->client_id = xx

        $cs->save();

        $this->client_subscription = $cs;

    }

    public function triggerWebhook($payment_hash)
    {
        //hit the webhook to after a successful onboarding
        //$client = xxxxxxx
        //todo webhook
        
        $body = [
            'billing_subscription' => $this->billing_subscription,
            'client_subscription' => $this->client_subscription,
        //    'client' => $client->toArray(),
        ];


        $client =  new \GuzzleHttp\Client(['headers' => $this->billing_subscription->webhook_configuration->post_purchase_headers]);

        $response = $client->{$this->billing_subscription->webhook_configuration->post_purchase_rest_method}($this->billing_subscription->post_purchase_url,[
            RequestOptions::JSON => ['body' => $body]
        ]);

            SystemLogger::dispatch(
                $body,
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_RESPONSE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                //$client,
            );

    }

    public function fireNotifications()
    {
        //scan for any notification we are required to send
    }


}
