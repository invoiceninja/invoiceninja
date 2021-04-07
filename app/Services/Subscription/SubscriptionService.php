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
use App\Factory\RecurringInvoiceFactory;
use App\Jobs\Util\SubscriptionWebhookHandler;
use App\Jobs\Util\SystemLogger;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientSubscription;
use App\Models\Invoice;
use App\Models\PaymentHash;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Repositories\InvoiceRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\SubscriptionRepository;
use App\Utils\Ninja;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SubscriptionHooker;
use GuzzleHttp\RequestOptions;

class SubscriptionService
{
    use MakesHash;
    use CleanLineItems;
    use SubscriptionHooker;

    /** @var subscription */
    private $subscription;

    /** @var client_subscription */
    // private $client_subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function completePurchase(PaymentHash $payment_hash)
    {

        if (!property_exists($payment_hash->data, 'billing_context')) {
            throw new \Exception("Illegal entrypoint into method, payload must contain billing context");
        }

        // if we have a recurring product - then generate a recurring invoice
        if(strlen($this->subscription->recurring_product_ids) >=1){

            $recurring_invoice = $this->convertInvoiceToRecurring($payment_hash->payment->client_id);
            $recurring_invoice_repo = new RecurringInvoiceRepository();

            $recurring_invoice->next_send_date = now();
            $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
            $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();

            /* Start the recurring service */
            $recurring_invoice->service()
                              ->start()
                              ->save();

            //execute any webhooks

            $context = [
                'context' => 'recurring_purchase',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
            ];

            $response = $this->triggerWebhook($context);

            nlog($response);

            if(array_key_exists('post_purchase_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['post_purchase_url']) >=1)
                return redirect($this->subscription->webhook_configuration['post_purchase_url']);

            return redirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);
        }
        else
        {
            $invoice = Invoice::find($payment_hash->fee_invoice_id);

            $context = [
                'context' => 'single_purchase',
                'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
                'client'  => $invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
            ];

            //execute any webhooks
            $this->triggerWebhook($context);

            if(array_key_exists('post_purchase_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['post_purchase_url']) >=1)
                return redirect($this->subscription->webhook_configuration['post_purchase_url']);

            return redirect('/client/invoices/'.$this->encodePrimaryKey($payment_hash->fee_invoice_id));

        }
    }

    /* Hits the client endpoint to determine whether the user is able to access this subscription */
    public function isEligible($contact)
    {

        $context = [
            'context' => 'is_eligible',
            'subscription' => $this->subscription->hashed_id,
            'contact' => $contact->hashed_id,
            'contact_email' => $contact->email
        ];

        $response = $this->triggerWebhook($context);

        return $response;
    }

    /* Starts the process to create a trial 
        - we create a recurring invoice, which is has its next_send_date as now() + trial_duration
        - we then hit the client API end point to advise the trial payload
        - we then return the user to either a predefined user endpoint, OR we return the user to the recurring invoice page.
    */
    public function startTrial(array $data)
    {
        // Redirects from here work just fine. Livewire will respect it.
        $client_contact = ClientContact::find($data['contact_id']);

        if(!$this->subscription->trial_enabled)
            return new \Exception("Trials are disabled for this product");

        //create recurring invoice with start date = trial_duration + 1 day
        $recurring_invoice_repo = new RecurringInvoiceRepository();

        $recurring_invoice = $this->convertInvoiceToRecurring($client_contact->client_id);
        $recurring_invoice->next_send_date = now()->addSeconds($this->subscription->trial_duration);
        $recurring_invoice->backup = 'is_trial';

        if(array_key_exists('coupon', $data) && ($data['coupon'] == $this->subscription->promo_code) && $this->subscription->promo_discount > 0)
        {
            $recurring_invoice->discount = $this->subscription->promo_discount;
            $recurring_invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        $recurring_invoice = $recurring_invoice_repo->save($data, $recurring_invoice);

        /* Start the recurring service */
        $recurring_invoice->service()
                          ->start()
                          ->save();

            $context = [
                'context' => 'trial',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
            ];

        //execute any webhooks
        $response = $this->triggerWebhook($context);

        if(array_key_exists('return_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['return_url']) >=1){
            return redirect($this->subscription->webhook_configuration['return_url']);
        }

        return redirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);
    }


    public function createInvoice($data): ?\App\Models\Invoice
    {

        $invoice_repo = new InvoiceRepository();
        $subscription_repo = new SubscriptionRepository();

        $invoice = InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $invoice->line_items = $subscription_repo->generateLineItems($this->subscription);
        $invoice->subscription_id = $this->subscription->id;

        if(strlen($data['coupon']) >=1 && ($data['coupon'] == $this->subscription->promo_code) && $this->subscription->promo_discount > 0)
        {
            $invoice->discount = $this->subscription->promo_discount;
            $invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        return $invoice_repo->save($data, $invoice);

    }


    private function convertInvoiceToRecurring($client_id)
    {

        $subscription_repo = new SubscriptionRepository();

        $recurring_invoice = RecurringInvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $recurring_invoice->client_id = $client_id;
        $recurring_invoice->line_items = $subscription_repo->generateLineItems($this->subscription, true);
        $recurring_invoice->subscription_id = $this->subscription->id;
        $recurring_invoice->frequency_id = $this->subscription->frequency_id ?: RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->date = now();
        $recurring_invoice->remaining_cycles = -1;

        return $recurring_invoice;
    }

    public function triggerWebhook($context)
    {
        /* If no webhooks have been set, then just return gracefully */
        if(!array_key_exists('post_purchase_url', $this->subscription->webhook_configuration) || !array_key_exists('post_purchase_rest_method', $this->subscription->webhook_configuration)) {
            return true;
        }

        $response = false;

        $body = array_merge($context, [
            'company_key' => $this->subscription->company->company_key, 
            'account_key' => $this->subscription->company->account->key,
            'db' => $this->subscription->company->db,
        ]);        

        $response = $this->sendLoad($this->subscription, $body);

        /* Append the response to the system logger body */
        if(is_array($response)){

            $body = $response;
        
        }
        else {
            
            $status = $response->getStatusCode();
            $response_body = $response->getBody();
            $body = array_merge($body, ['status' => $status, 'response_body' => $response_body]);

        }

        $client = \App\Models\Client::find($this->decodePrimaryKey($body['client']));

            SystemLogger::dispatch(
                $body,
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_RESPONSE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $client,
            );

        return $response;

    }

    public function fireNotifications()
    {
        //scan for any notification we are required to send
    }

    public function products()
    {
        return Product::whereIn('id', $this->transformKeys(explode(",", $this->subscription->product_ids)))->get();
    }

    public function recurring_products()
    {
        return Product::whereIn('id', $this->transformKeys(explode(",", $this->subscription->recurring_product_ids)))->get();
    }
}
