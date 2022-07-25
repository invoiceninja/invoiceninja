<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe\Jobs;

use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentIntentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Utilities;

    public $tries = 1; //number of retries

    public $deleteWhenMissingModels = true;

    public $stripe_request;

    public $company_key;

    private $company_gateway_id;

    public $payment_completed = false;

    public function __construct($stripe_request, $company_key, $company_gateway_id)
    {
        $this->stripe_request = $stripe_request;
        $this->company_key = $company_key;
        $this->company_gateway_id = $company_gateway_id;
    }

    public function handle()
    {
        
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company = Company::where('company_key', $this->company_key)->first();

            foreach ($this->stripe_request as $transaction) {

                if(array_key_exists('payment_intent', $transaction))
                {
                    $payment = Payment::query()
                        ->where('company_id', $company->id)
                        ->where(function ($query) use ($transaction) {
                            $query->where('transaction_reference', $transaction['payment_intent'])
                                  ->orWhere('transaction_reference', $transaction['id']);
                                })
                        ->first();
                }
                else
                {
                     $payment = Payment::query()
                        ->where('company_id', $company->id)
                        ->where('transaction_reference', $transaction['id'])
                        ->first();
                }

                if ($payment) {
                    $payment->status_id = Payment::STATUS_COMPLETED;
                    $payment->save();
    
                    $this->payment_completed = true;
                }
            }


        if($this->payment_completed)
            return;


        if(optional($this->stripe_request['object']['charges']['data'][0])['id']){

            $company = Company::where('company_key', $this->company_key)->first();

            $payment = Payment::query()
                             ->where('company_id', $company->id)
                             ->where('transaction_reference', $this->stripe_request['object']['charges']['data'][0]['id'])
                             ->first();

             //return early
            if($payment && $payment->status_id == Payment::STATUS_COMPLETED){
                nlog(" payment found and status correct - returning "); 
                return;
            }
            elseif($payment){
                $payment->status_id = Payment::STATUS_COMPLETED;
                $payment->save();
            }


            $hash = optional($this->stripe_request['object']['charges']['data'][0]['metadata'])['payment_hash'];

            $payment_hash = PaymentHash::where('hash', $hash)->first();

            if(!$payment_hash)
                return;

            nlog("payment intent");
            nlog($this->stripe_request);

            if(array_key_exists('allowed_source_types', $this->stripe_request['object']) && optional($this->stripe_request['object']['charges']['data'][0]['metadata']['payment_hash']) && in_array('card', $this->stripe_request['object']['allowed_source_types']))
            {
                nlog("hash found");

                $hash = $this->stripe_request['object']['charges']['data'][0]['metadata']['payment_hash'];

                $payment_hash = PaymentHash::where('hash', $hash)->first();
                $invoice = Invoice::with('client')->find($payment_hash->fee_invoice_id);
                $client = $invoice->client;

                $this->updateCreditCardPayment($payment_hash, $client);
            }
            elseif(array_key_exists('payment_method_types', $this->stripe_request['object']) && optional($this->stripe_request['object']['charges']['data'][0]['metadata']['payment_hash']) && in_array('card', $this->stripe_request['object']['payment_method_types']))
            {
                nlog("hash found");

                $hash = $this->stripe_request['object']['charges']['data'][0]['metadata']['payment_hash'];

                $payment_hash = PaymentHash::where('hash', $hash)->first();
                $invoice = Invoice::with('client')->find($payment_hash->fee_invoice_id);
                $client = $invoice->client;

                $this->updateCreditCardPayment($payment_hash, $client);
            }

        }


        SystemLogger::dispatch(
            ['response' => $this->stripe_request, 'data' => []],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            null,
            $company,
        );


    }

    private function updateCreditCardPayment($payment_hash, $client)
    {
        $company_gateway = CompanyGateway::find($this->company_gateway_id);
        $payment_method_type = optional($this->stripe_request['object']['charges']['data'][0]['metadata'])['gateway_type_id'];
        $driver = $company_gateway->driver($client)->init()->setPaymentMethod($payment_method_type);

        $payment_hash->data = array_merge((array) $payment_hash->data, $this->stripe_request);
        $payment_hash->save();
        $driver->setPaymentHash($payment_hash);

        $data = [
            'payment_method' => $payment_hash->data->object->payment_method,
            'payment_type' => PaymentType::parseCardType(strtolower(optional($this->stripe_request['object']['charges']['data'][0]['payment_method_details']['card'])['brand'])) ?: PaymentType::CREDIT_CARD_OTHER,
            'amount' => $payment_hash->data->amount_with_fee,
            'transaction_reference' => $this->stripe_request['object']['charges']['data'][0]['id'],
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];
        
        $payment = $driver->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe_request, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $client,
            $client->company,
        );

    }

}