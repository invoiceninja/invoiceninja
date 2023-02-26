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

namespace App\PaymentDrivers\Stripe;

use App\Utils\Number;
use App\Models\Payment;
use App\Models\SystemLog;
use Stripe\PaymentIntent;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\StripePaymentDriver;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class BankTransfer
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function paymentView(array $data)
    {
        $this->stripe->init();

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->currency()->code,
            'customer' => $this->stripe->findOrCreateCustomer()->id,
            'description' => $this->stripe->getDescription(false),
            'payment_method_types' => ['customer_balance'],
            'payment_method_data' => [
                'type' => 'customer_balance',
            ],
            'payment_method_options' => [
                'customer_balance' => [
                'funding_type' => 'bank_transfer',
                'bank_transfer' => $this->resolveBankType()
                ],
            ],
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::DIRECT_DEBIT,
            ],
        ], $this->stripe->stripe_connect_auth);


        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency())]);
        $this->stripe->payment_hash->save();

        $data = [];
        $data['return_url'] = $this->buildReturnUrl();
        $data['gateway'] = $this->stripe;
        $data['client_secret'] = $intent ? $intent->client_secret : false;
        
        return render('gateways.stripe.bank_transfer.pay', $data);
    }
    
    /**
     * Resolve the bank type based on the currency
     *
     * @return void
     */
    private function resolveBankType()
    {

        return match($this->stripe->client->currency()->code){
            'GBP' =>  ['type' => 'gb_bank_transfer'],
            'EUR' => ['type' => 'eu_bank_transfer', 'eu_bank_transfer' => ['country' => $this->stripe->client->country->iso_3166_2]],
            'JPY' => ['type' => 'jp_bank_transfer'],
            'MXN' => ['type' =>'mx_bank_transfer'],
        };

    }
    
    /**
     * Return URL
     *
     * @return string
     */
    private function buildReturnUrl(): string
    {
        return route('client.payments.response.get', [
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_method_id' => GatewayType::DIRECT_DEBIT,
        ]);
    }


    public function paymentResponse(PaymentResponseRequest $request)
    {
        
        $this->stripe->init();

        $this->stripe->setPaymentHash($request->getPaymentHash());
        $this->stripe->client = $this->stripe->payment_hash->fee_invoice->client;

        if($request->payment_intent){

            $pi = \Stripe\PaymentIntent::retrieve(
                $request->payment_intent,
                $this->stripe->stripe_connect_auth
            );

            if (in_array($pi->status, ['succeeded', 'processing'])) {
                return $this->processSuccesfulRedirect($pi);
            }

            /*  Create a pending payment */
            if($pi->status == 'requires_action' && $pi->next_action->type == 'display_bank_transfer_instructions') {

                $data = [
                    'amount' => Number::formatMoney($this->stripe->convertFromStripeAmount($pi->next_action->display_bank_transfer_instructions->amount_remaining, $this->stripe->client->currency()->precision, $this->stripe->client->currency()), $this->stripe->client),
                    'account_holder_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->sort_code->account_holder_name,
                    'account_number' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->sort_code->account_number,
                    'sort_code' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->sort_code->sort_code,
                    'reference' => $pi->next_action->display_bank_transfer_instructions->reference,
                    'description' => $pi->description,
                    'gateway'   => $this->stripe->company_gateway,

                ];
                
                return render('gateways.stripe.bank_transfer.bank_details', $data);

                // $data = [
                //     'payment_method' => $pi->payment_method,
                //     'payment_type' => PaymentType::DIRECT_DEBIT,
                //     'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
                //     'transaction_reference' => $pi->id,
                //     'gateway_type_id' => GatewayType::DIRECT_DEBIT,

                // ];

                // $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

                // SystemLogger::dispatch(
                //     ['response' => $this->stripe->payment_hash->data, 'data' => $data],
                //     SystemLog::CATEGORY_GATEWAY_RESPONSE,
                //     SystemLog::EVENT_GATEWAY_SUCCESS,
                //     SystemLog::TYPE_STRIPE,
                //     $this->stripe->client,
                //     $this->stripe->client->company,
                // );

                // return redirect($pi->next_action->display_bank_transfer_instructions->hosted_instructions_url);

            }

            return $this->processUnsuccesfulRedirect();

        }

    }

    public function processSuccesfulRedirect($payment_intent)
    {
        $this->stripe->init();

        $data = [
            'payment_method' => $payment_intent->payment_method,
            'payment_type' => PaymentType::DIRECT_DEBIT,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent->id,
            'gateway_type_id' => GatewayType::DIRECT_DEBIT,

        ];

        $payment = $this->stripe->createPayment($data, $payment_intent->status == 'processing' ? Payment::STATUS_PENDING : Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccesfulRedirect()
    {
        $server_response = $this->stripe->payment_hash->data;

        $this->stripe->sendFailureMail($server_response->redirect_status);

        $message = [
            'server_response' => $server_response,
            'data' => $this->stripe->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }


}