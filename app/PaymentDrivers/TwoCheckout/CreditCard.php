<?php


namespace App\PaymentDrivers\TwoCheckout;


use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\TwoCheckoutPaymentDriver;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Checkout\Models\Payments\Payment;
use Checkout\Models\Payments\TokenSource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CreditCard implements MethodInterface
{
    public $twoCheckout;

    public function __construct(TwoCheckoutPaymentDriver $twoCheckout)
    {
        $this->twoCheckout = $twoCheckout;
    }

    public function authorizeView(array $data)
    {
        // TODO: Implement authorizeView() method.
    }

    public function authorizeResponse(Request $request)
    {
        // TODO: Implement authorizeResponse() method.
    }

    public function paymentView(array $data)
    {
        $data['gateway'] = $this->twoCheckout;
        $data['currency'] = $this->twoCheckout->client->getCurrencyCode();
        $data['raw_value'] = $data['total']['amount_with_fee'];
        return render('gateways.twocheckout.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $state = [
            'raw_value' => $request->raw_value,
            'currency' => $request->currency,
            'payment_hash' => $request->payment_hash,
            'client_id' => $this->twoCheckout->client->id,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);


        $this->twoCheckout->payment_hash->data = array_merge((array)$this->twoCheckout->payment_hash->data, $state);
        $this->twoCheckout->payment_hash->save();

        return $this->attemptPaymentUsingCreditCard($request);
    }

    private function attemptPaymentUsingCreditCard(PaymentResponseRequest $request)
    {

        $method = new TokenSource(
            $this->twoCheckout->payment_hash->data->token
        );

        return $this->completePayment($method, $request);
    }

    private function completePayment($method, PaymentResponseRequest $request)
    {

        $payment = new Payment($method, $this->twoCheckout->payment_hash->data->currency);
        $payment->amount = $this->twoCheckout->payment_hash->data->amount_with_fee;
        //todo set in config instead of hardcode
        $api_url = 'https://api.2checkout.com/rest/6.0/orders';


        $merchantCode = $this->twoCheckout->company_gateway->getConfig()->merchantCode;
        $secretCode = $this->twoCheckout->company_gateway->getConfig()->secretCode;


        $string = strlen($merchantCode) . $merchantCode . strlen(gmdate('Y-m-d H:i:s')) . gmdate('Y-m-d H:i:s');

        $hash = hash_hmac('md5', $string, $secretCode);

        try {
            $response = Http::withHeaders([
                'X-Avangate-Authentication' => 'code=' . $merchantCode . ' date=' . gmdate('Y-m-d H:i:s') . ' hash=' . $hash . '',
                'Accept' => 'application/json'
            ])->post($api_url, [
                'Language' => 'en',
                'Country' => 'US',
                'CustomerIp' => '10.41.23.11',
                'source' => 'ninja',
                'ExternalCustomerReference' => $this->twoCheckout->getDescription(),
                'Currency' => 'USD',
                'MachineId' => '123124',
                "Items" => [
                    "Code" => '14123',
                    "Quantity" => 1,
                ],
                "BillingDetails" => [
                    "FirstName" => $this->twoCheckout->client->present()->name,
                    //TODO seperate
                    "LastName" => $this->twoCheckout->client->present()->name,
                    "CountryCode" => 'US',
                    "State" => "California",
                    "City" => 'San Francisco',
                    "Address1" => "Example Steert",
                    "Zip" => "90210",
                    "Email" => $this->twoCheckout->client->present()->email,
                ],
                "PaymentDetails" => [
                    "Type" => "EES_TOKEN_PAYMENT",
                    "Currency" => 'USD',
                    "PaymentMethod" => [
                        "EesToken" => $this->twoCheckout->payment_hash->data->token,
                        "Vendor3DSReturnURL" => route('two_checkout.3ds_return'),
                        "Vendor3DSCancelURL" => route('two_checkout.3ds_cancel'),
                    ]
                ]
            ]);
            return $response;

        } catch
        (\Exception $e) {

            $this->twoCheckout->sendFailureMail($e->getMessage());

            $message = [
                'server_response' => $e->getMessage(),
                'data' => $this->twoCheckout->payment_hash->data,
            ];

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_WEPAY,
                $this->twoCheckout->client,
                $this->twoCheckout->client->company,
            );

            throw new PaymentFailed($e->getMessage(), 500);

        }


    }
}
