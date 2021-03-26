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

namespace App\Services\Subscription;

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceToRecurringInvoiceFactory;
use App\Jobs\Util\SystemLogger;
use App\Models\Subscription;
use App\Models\ClientContact;
use App\Models\ClientSubscription;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\Product;
use App\Models\SystemLog;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use GuzzleHttp\RequestOptions;

class SubscriptionService
{
    use MakesHash;
    use CleanLineItems;

    /** @var subscription */
    private $subscription;

    /** @var client_subscription */
    private $client_subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function completePurchase(PaymentHash $payment_hash)
    {

        if (!property_exists($payment_hash->data, 'billing_context')) {
            throw new \Exception("Illegal entrypoint into method, payload must contain billing context");
        }

        // At this point we have some state carried from the billing page
        // to this, available as $payment_hash->data->billing_context. Make something awesome ⭐

        // create client subscription record
        //
        // create recurring invoice if is_recurring
        //


    }

    /**
        'email' => $this->email ?? $this->contact->email,
        'quantity' => $this->quantity,
        'contact_id' => $this->contact->id,
     */
    public function startTrial(array $data)
    {
        // Redirects from here work just fine. Livewire will respect it.

        if(!$this->subscription->trial_enabled)
            return new \Exception("Trials are disabled for this product");

        $contact = ClientContact::with('client')->find($data['contact_id']);

        $cs = new ClientSubscription();
        $cs->subscription_id = $this->subscription->id;
        $cs->company_id = $this->subscription->company_id;
        $cs->trial_started = time();
        $cs->trial_ends = time() + $this->subscription->trial_duration;
        $cs->quantity = $data['quantity'];
        $cs->client_id = $contact->client->id;
        $cs->save();

        $this->client_subscription = $cs;

        //execute any webhooks
        $this->triggerWebhook();

        if(strlen($this->subscription->webhook_configuration->post_purchase_url) >=1)
            return redirect($this->subscription->webhook_configuration->post_purchase_url);

        return redirect('/client/subscription/'.$cs->hashed_id);
    }

    public function createInvoice($data): ?\App\Models\Invoice
    {

        $invoice_repo = new InvoiceRepository();

        $data['line_items'] = $this->cleanItems($this->createLineItems($data));

        return $invoice_repo->save($data, InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id));

    }

    /**
     * Creates the required line items for the invoice 
     * for the billing subscription.
     */
    private function createLineItems($data): array
    {

        $line_items = [];

        $product = $this->subscription->product;

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
        if(strlen($data['coupon']) >=1 && ($data['coupon'] == $this->subscription->promo_code) && $this->subscription->promo_discount > 0) 
            $line_items[] = $this->createPromoLine($data);

        return $line_items;

    }

    /**
     * If a coupon is entered (and is valid)
     * then we apply the coupon discount with a line item.
     */
    private function createPromoLine($data)
    {
        
        $product = $this->subscription->product;
        $discounted_amount = 0;
        $discount = 0;
        $amount = $data['quantity'] * $product->cost;

        if ($this->subscription->is_amount_discount == true) {
            $discount = $this->subscription->promo_discount;
        }
        else {
            $discount = round($amount * ($this->subscription->promo_discount / 100), 2);
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
        $invoice = Invoice::find($payment_hash->billing_context->invoice_id); 
        
        if(!$invoice)
            throw new \Exception("Could not match an invoice for payment of billing subscription");

        //todo - need to remove the promo code - if it exists
        
        return InvoiceToRecurringInvoiceFactory::create($invoice);
        
    }

    public function createClientSubscription($payment_hash)
    {

        //is this a recurring or one off subscription.

        $cs = new ClientSubscription();
        $cs->subscription_id = $this->subscription->id;
        $cs->company_id = $this->subscription->company_id;

        $cs->invoice_id = $payment_hash->billing_context->invoice_id;
        $cs->client_id = $payment_hash->billing_context->client_id;
        $cs->quantity = $payment_hash->billing_context->quantity;

            //if is_recurring
            //create recurring invoice from invoice
            if($this->subscription->is_recurring)
            {
            $recurring_invoice = $this->convertInvoiceToRecurring($payment_hash);
            $recurring_invoice->frequency_id = $this->subscription->frequency_id;
            $recurring_invoice->next_send_date = $recurring_invoice->nextDateByFrequency(now()->format('Y-m-d'));
            $recurring_invoice->save();
            $cs->recurring_invoice_id = $recurring_invoice->id;

            //?set the recurring invoice as active - set the date here also based on the frequency?
            $recurring_invoice->service()->start();
            }


        $cs->save();

        $this->client_subscription = $cs;

    }

    public function triggerWebhook()
    {
        //hit the webhook to after a successful onboarding

        $body = [
            'subscription' => $this->subscription,
            'client_subscription' => $this->client_subscription,
            'client' => $this->client_subscription->client->toArray(),
        ];


        $client =  new \GuzzleHttp\Client(['headers' => $this->subscription->webhook_configuration->post_purchase_headers]);

        $response = $client->{$this->subscription->webhook_configuration->post_purchase_rest_method}($this->subscription->post_purchase_url,[
            RequestOptions::JSON => ['body' => $body]
        ]);

            SystemLogger::dispatch(
                $body,
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_RESPONSE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->client_subscription->client,
            );

    }

    public function fireNotifications()
    {
        //scan for any notification we are required to send
    }

    public function products()
    {
        return Product::whereIn('id', explode(",", $this->subscription->product_ids))->get();
    }

    public function recurring_products()
    {
        return Product::whereIn('id', explode(",", $this->subscription->recurring_product_ids))->get();
    }

    public function price()
    {
        return 1;
    }

}
