<?php

namespace App\Ninja\PaymentDrivers;

use Omnipay;
use Session;
use App\Models\Payment;

class GoCardlessV2RedirectPaymentDriver extends BasePaymentDriver
{
    protected $transactionReferenceParam = "\x00*\x00id";

    public function gatewayTypes()
    {
        $types = [
            GATEWAY_TYPE_GOCARDLESS,
            GATEWAY_TYPE_TOKEN,
        ];

        return $types;
    }

    // Workaround for access_token/accessToken issue
    protected function gateway()
    {
        if ($this->gateway) {
            return $this->gateway;
        }

        $this->gateway = Omnipay::create($this->accountGateway->gateway->provider);

        $config = (array) $this->accountGateway->getConfig();
        $config['access_token'] = $config['accessToken'];
        $config['secret'] = $config['webhookSecret'];
        $this->gateway->initialize($config);

        if (isset($config['testMode']) && $config['testMode']) {
            $this->gateway->setTestMode(true);
        }

        return $this->gateway;
    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails($paymentMethod);

        if ($paymentMethod) {
            $data['mandate_reference'] = $paymentMethod->source_reference;
        }

        if ($ref = request()->redirect_flow_id) {
            $data['transaction_reference'] = $ref;
        }

        return $data;
    }

    protected function shouldCreateToken()
    {
        return false;
    }

    public function completeOffsitePurchase($input)
    {
        $details = $this->paymentDetails();
        $this->purchaseResponse = $response = $this->gateway()->completePurchase($details)->send();

        if (! $response->isSuccessful()) {
            return false;
        }

        $paymentMethod = $this->createToken();
        $payment = $this->completeOnsitePurchase(false, $paymentMethod);

        return $payment;
    }

    protected function creatingCustomer($customer)
    {
        $customer->token = $this->purchaseResponse->getCustomerId();

        return $customer;
    }

    protected function creatingPaymentMethod($paymentMethod)
    {
        $paymentMethod->source_reference = $this->purchaseResponse->getMandateId();
        $paymentMethod->payment_type_id = PAYMENT_TYPE_GOCARDLESS;
        $paymentMethod->status = PAYMENT_METHOD_STATUS_VERIFIED;

        return $paymentMethod;
    }

    protected function creatingPayment($payment, $paymentMethod)
    {
        $payment->payment_status_id = PAYMENT_STATUS_PENDING;

        return $payment;
    }

    public function handleWebHook($input)
    {
        $accountGateway = $this->accountGateway;
        $accountId = $accountGateway->account_id;

        $token = $accountGateway->getConfigField('webhookSecret');
        $rawPayload = file_get_contents('php://input');
        $providedSignature = $_SERVER['HTTP_WEBHOOK_SIGNATURE'];
        $calculatedSignature = hash_hmac('sha256', $rawPayload, $token);

        if (! hash_equals($providedSignature, $calculatedSignature)) {
            throw new \Exception('Signature does not match');
        }

        foreach ($input['events'] as $event) {
            $type = $event['resource_type'];
            $action = $event['action'];

            $supported = [
                'paid_out',
                'failed',
                'charged_back',
            ];

            if ($type != 'payments' || ! in_array($action, $supported)) {
                continue;
            }

            $sourceRef = isset($event['links']['payment']) ? $event['links']['payment'] : false;
            $payment = Payment::scope(false, $accountId)->where('transaction_reference', '=', $sourceRef)->first();

            if (! $payment) {
                continue;
            }

            if ($payment->is_deleted || $payment->invoice->is_deleted) {
                continue;
            }

            if ($action == 'failed' || $action == 'charged_back') {
                if (! $payment->isFailed()) {
                    $payment->markFailed($event['details']['description']);

                    $userMailer = app('App\Ninja\Mailers\UserMailer');
                    $userMailer->sendNotification($payment->user, $payment->invoice, 'payment_failed', $payment);
                }
            } elseif ($action == 'paid_out') {
                $payment->markComplete();
            }
        }
    }
}
