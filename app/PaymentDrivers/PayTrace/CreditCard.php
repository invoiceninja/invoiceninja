<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\PayTrace;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\PayFastPaymentDriver;
use App\PaymentDrivers\PaytracePaymentDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreditCard
{

    public $paytrace_driver;

    public function __construct(PaytracePaymentDriver $paytrace_driver)
    {
        $this->paytrace_driver = $paytrace_driver;
    }

    public function authorizeView($data)
    {
        
        $data['client_key'] = $this->paytrace_driver->getAuthToken();

        return render('gateways.paytrace.authorize', $data);
    }


 	public function authorizeResponse($request)
 	{
        $data = $request->all();

        return response()->json([], 200);

 	}  

    public function paymentView($data)
    {


        return render('gateways.paytrace.pay', $data);

    }


    public function paymentResponse(Request $request)
    {
        $response_array = $request->all();



        // if($response_array['payment_status'] == 'COMPLETE') {

        //     $this->payfast->logSuccessfulGatewayResponse(['response' => $response_array, 'data' => $this->paytrace_driver->payment_hash], SystemLog::TYPE_PAYFAST);

        //     return $this->processSuccessfulPayment($response_array);
        // }
        // else {
        //     $this->processUnsuccessfulPayment($response_array);
        // }
    }

    private function processSuccessfulPayment($response_array)
    {



        // $payment = $this->paytrace_driver->createPayment($payment_record, Payment::STATUS_COMPLETED);


    }

    private function processUnsuccessfulPayment($server_response)
    {
        // PaymentFailureMailer::dispatch($this->paytrace_driver->client, $server_response->cancellation_reason, $this->paytrace_driver->client->company, $server_response->amount);

        // PaymentFailureMailer::dispatch(
        //     $this->paytrace_driver->client,
        //     $server_response,
        //     $this->paytrace_driver->client->company,
        //     $server_response['amount_gross']
        // );

        // $message = [
        //     'server_response' => $server_response,
        //     'data' => $this->paytrace_driver->payment_hash->data,
        // ];

        // SystemLogger::dispatch(
        //     $message,
        //     SystemLog::CATEGORY_GATEWAY_RESPONSE,
        //     SystemLog::EVENT_GATEWAY_FAILURE,
        //     SystemLog::TYPE_PAYFAST,
        //     $this->payfast->client,
        //     $this->payfast->client->company,
        // );

        // throw new PaymentFailed('Failed to process the payment.', 500);
    }

}