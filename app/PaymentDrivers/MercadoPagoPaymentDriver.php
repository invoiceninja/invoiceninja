<?php

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use MercadoPago\Payer;
use MercadoPago\Payment as MPayment;
use MercadoPago\SDK;

class MercadoPagoPaymentDriver extends BaseDriver
{
    private Client $http;

    /**
     * MercadoPagoPaymentDriver constructor.
     *
     * @param CompanyGateway $company_gateway
     * @param \App\Models\Client|null $client
     * @param bool $invitation
     */
    public function __construct(CompanyGateway $company_gateway, \App\Models\Client $client = null, $invitation = false)
    {
        parent::__construct($company_gateway, $client, $invitation);
        $this->http = app(Client::class);
        SDK::setAccessToken(config('mercadopago.accessToken'));
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
    }

    /**
     * Process a payment
     *
     * @param  array $data
     * @return mixed Return a view for the payment
     */
    public function processPaymentView(array $data)
    {
        return render('gateways.mercadopago.pay', $data);
    }

    /**
     * Process payment response
     *
     * @param  Request $request
     * @return mixed   Return a response for the payment
     */
    public function processPaymentResponse(Request $request)
    {

        try {
            $payment = new MPayment();
            $payment->transaction_amount = (float)$_POST['transactionAmount'];
            $payment->token = $_POST['token'];
            $payment->description = $_POST['description'];
            $payment->installments = (int)$_POST['installments'];
            $payment->payment_method_id = $_POST['paymentMethodId'];
            $payment->issuer_id = (int)$_POST['issuer'];

            $payer = new Payer();
            $payer->email = $_POST['email'];
            $payer->identification = array(
                "type" => $_POST['docType'],
                "number" => $_POST['docNumber']
            );
            $payment->payer = $payer;
            $payment->save();

            $data['gateway_type_id'] = GatewayType::CREDIT_CARD;
            $data['amount'] = $request->amount_with_fee;
            $data['payment_type'] = PaymentType::CREDIT;
            $data['transaction_reference'] = $response->transaction_id;
            $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);
            return redirect('client/invoices');
        } catch (Exception $e) {

        }
    }

    /**
     * Executes a refund attempt for a given amount with a transaction_reference.
     *
     * @param Payment $payment The Payment Object
     * @param $refund_amount
     * @param bool $return_client_response Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed
     */
    public function refund(Payment $payment, $refund_amount, $return_client_response = false)
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
