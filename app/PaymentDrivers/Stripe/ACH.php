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

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\Request;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Mail\Gateways\ACHVerificationNotification;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Exception;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;

class ACH
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->stripe;

        return render('gateways.stripe.ach.authorize', array_merge($data));
    }

    public function authorizeResponse(Request $request)
    {
        $this->stripe->init();

        $stripe_response = json_decode($request->input('gateway_response'));

        $customer = $this->stripe->findOrCreateCustomer();

        try {
            $source = $this->stripe->stripe->customers->createSource($customer->id, ['source' => $stripe_response->token->id]);
        } catch (InvalidRequestException $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }

        $client_gateway_token = $this->storePaymentMethod($source, $request->input('method'), $customer);

        $mailer = new NinjaMailerObject();
        $mailer->mailable = new ACHVerificationNotification();
        $mailer->company = auth('contact')->user()->client->company;
        $mailer->settings = auth('contact')->user()->client->company->settings;
        $mailer->to_user = auth('contact')->user();

        NinjaMailerJob::dispatchNow($mailer);

        return redirect()->route('client.payment_methods.verification', ['payment_method' => $client_gateway_token->hashed_id, 'method' => GatewayType::BANK_TRANSFER]);
    }

    public function verificationView(ClientGatewayToken $token)
    {
        $data = [
            'token' => $token,
            'gateway' => $this->stripe,
        ];

        return render('gateways.stripe.ach.verify', $data);
    }

    public function processVerification(Request $request, ClientGatewayToken $token)
    {
        $this->stripe->init();

        $bank_account = Customer::retrieveSource($request->customer, $request->source);

        try {
            $bank_account->verify(['amounts' => request()->transactions]);

            $token->meta->verified_at = now();
            $token->save();

            return redirect()
                ->route('client.invoices.index')
                ->with('success', __('texts.payment_method_verified'));
        } catch (CardException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->stripe;
        $data['currency'] = $this->stripe->client->getCurrencyCode();
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['customer'] = $this->stripe->findOrCreateCustomer();
        $data['amount'] = $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision);

        return render('gateways.stripe.ach.pay', $data);
    }


    public function paymentResponse($request)
    {
        $this->stripe->init();

        $source = ClientGatewayToken::query()
            ->where('id', $this->decodePrimaryKey($request->source))
            ->where('company_id', auth('contact')->user()->client->company->id)
            ->first();

        if (!$source) {
            throw new PaymentFailed(ctrans('texts.payment_token_not_found'), 401);
        }

        $state = [
            'payment_method' => $request->payment_method_id,
            'gateway_type_id' => $request->company_gateway_id,
            'amount' => $this->stripe->convertToStripeAmount($request->amount, $this->stripe->client->currency()->precision),
            'currency' => $request->currency,
            'customer' => $request->customer,
        ];

        $state = array_merge($state, $request->all());
        $state['source'] = $source->token;

        $this->stripe->payment_hash->data = array_merge((array)$this->stripe->payment_hash->data, $state);
        $this->stripe->payment_hash->save();

        try {
            $state['charge'] = \Stripe\Charge::create([
                'amount' => $state['amount'],
                'currency' => $state['currency'],
                'customer' => $state['customer'],
                'source' => $state['source'],
            ]);

            $state = array_merge($state, $request->all());

            $this->stripe->payment_hash->data = array_merge((array)$this->stripe->payment_hash->data, $state);
            $this->stripe->payment_hash->save();

            if ($state['charge']->status === 'pending' && is_null($state['charge']->failure_message)) {
                return $this->processPendingPayment($state);
            }

            return $this->processUnsuccessfulPayment($state);
        } catch (Exception $e) {
            if ($e instanceof CardException) {
                return redirect()->route('client.payment_methods.verification', ['id' => ClientGatewayToken::first()->hashed_id, 'method' => GatewayType::BANK_TRANSFER]);
            }

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    public function processPendingPayment($state)
    {
        $this->stripe->init();

        $data = [
            'payment_method' => $state['source'],
            'payment_type' => PaymentType::ACH,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->amount, $this->stripe->client->currency()->precision),
            'transaction_reference' => $state['charge']->id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $state['charge'], 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client
        );

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($state)
    {

        PaymentFailureMailer::dispatch(
            $this->stripe->client,
            $state['charge'],
            $this->stripe->client->company,
            $state['amount']
        );

        $message = [
            'server_response' => $state['charge'],
            'data' => $this->stripe->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    private function storePaymentMethod($method, $payment_method_id, $customer)
    {
        try {
            $payment_meta = new \stdClass;
            $payment_meta->brand = (string)sprintf('%s (%s)', $method->bank_name, ctrans('texts.ach'));
            $payment_meta->last4 = (string)$method->last4;
            $payment_meta->type = GatewayType::BANK_TRANSFER;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => $payment_method_id,
            ];

            return $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);
        } catch (Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }
}
