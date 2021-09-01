<?php

namespace App\PaymentDrivers;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecebeAquiPaymentDriver extends BaseDriver
{
    private $http;
    private $base_url;

    /**
     * RecebeAquiPaymentDriver constructor.
     *
     * @param CompanyGateway $company_gateway
     * @param \App\Models\Client|null $client
     * @param bool $invitation
     */
    public function __construct(CompanyGateway $company_gateway, \App\Models\Client $client = null, $invitation = false)
    {
        parent::__construct($company_gateway, $client, $invitation);
        $this->http = app(Client::class);
        $this->base_url = config('app.env') == 'local' ? 'https://sandbox.api.recebeaqui.com/api/v3' : 'https://api.recebeaqui.com/api/v3';
    }

    /**
     * Authorize a payment method.
     *
     * Returns a reusable token for storage for future payments
     *
     * @param array $data
     * @return mixed Return a view for collecting payment method information
     */
    public function authorizeView(array $data)
    {
    }

    /**
     * The payment authorization response
     *
     * @param  Request $request
     * @return mixed Return a response for collecting payment method information
     */
    public function authorizeResponse(Request $request)
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Process a payment
     *
     * @param  array $data
     * @return mixed Return a view for the payment
     */
    public function processPaymentView(array $data)
    {
        $data['gateway'] = $this;
        $data['payment_hash'] = $this->payment_hash;
        $data['token_client'] = $this->company_gateway->getConfigField('tokenCliente');
        $data['id_pagamento_cliente'] = Str::uuid()->toString();
        return render('gateways.recebeaqui.pay', $data);
    }

    /**
     * Process payment response
     *
     * @param Request $request
     * @return mixed   Return a response for the payment
     * @throws PaymentFailed
     */
    public function processPaymentResponse(Request $request)
    {
        try {
            if($request->has('pagamentoNaoEfetuado')) {
                throw new Exception("Payment not finished, for: " . $request->get('pagamentoNaoEfetuado'));
            }
            $data['gateway_type_id'] = GatewayType::CREDIT_CARD;
            $data['amount'] = (float) $request->get('amount');
            $data['payment_type'] = PaymentType::CREDIT;
            $data['transaction_reference'] = $request->get('IdPagamentoCliente');
            $this->createPayment($data);
            return redirect('client/invoices');
        } catch (Exception $e) {
            PaymentFailureMailer::dispatch($this->client, $e->getMessage(), $this->company_gateway->company, $this->payment_hash);
            Log::error($e);
            throw new PaymentFailed('Failed to process the payment.', 500);
        }
    }

    /**
     * Executes a refund attempt for a given amount with a transaction_reference.
     *
     * @param Payment $payment The Payment Object
     * @param $amount
     * @param bool $return_client_response Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
    }

    /**
     * Process an unattended payment.
     *
     * @param ClientGatewayToken $cgt The client gateway token object
     * @param PaymentHash $payment_hash The Payment hash containing the payment meta data
     * @return void The payment response
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }

    /**
     * Set the inbound request payment method type for access.
     *
     * @param int $payment_method_id The Payment Method ID
     */
    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;
        return $this;
    }

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }
}
