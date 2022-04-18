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

namespace App\PaymentDrivers\Forte;

use App\Models\GatewayType;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Validator;
use App\PaymentDrivers\FortePaymentDriver;
use App\Models\Payment;

class ACH
{
    use MakesHash;
    
    public $forte;

    private $forte_base_uri="";
    private $forte_api_access_id="";
    private $forte_secure_key="";
    private $forte_auth_organization_id="";
    private $forte_organization_id="";
    private $forte_location_id="";
    
    public function __construct(FortePaymentDriver $forte)
    {
        $this->forte = $forte;
    }

    public function authorizeView(array $data)
    {
        return render('gateways.forte.ach.authorize', $data);
    }

    public function authorizeResponse(Request $request)
    {
        $customer_token = null;
        $request->validate([
            'account_number'=>'required|numeric',
            'account_holder_name'=>'required|string',
            'routing_number'=>'required|numeric',
        ]);
        if ($this->forte->client->gateway_tokens->count() == 0) {
            try {
                $curl = curl_init();

                curl_setopt_array($curl, array(
                CURLOPT_URL => $this->forte_base_uri.'organizations/'.$this->forte_organization_id.'/locations/'.$this->forte_location_id.'/customers/',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>'{
                    "first_name": "'.$this->forte->client->name.'",
                    "last_name": "'.$this->forte->client->name.'",
                    "company_name": "'.$this->forte->client->name.'",
                    "customer_id": "'.$this->forte->client->number.'"
                }',
                CURLOPT_HTTPHEADER => array(
                    'X-Forte-Auth-Organization-Id: '.$this->forte_organization_id,
                    'Content-Type: application/json',
                    'Authorization: Basic '.base64_encode($this->forte_api_access_id.':'.$this->forte_secure_key),
                    'Cookie: visid_incap_621087=QJCccwHeTHinK5DnAeQIuXPk5mAAAAAAQUIPAAAAAAATABmm7IZkHhUi85sN+UaS; nlbi_621087=eeFJXPvhGXW3XVl0R1efXgAAAAC5hY2Arn4aSDDQA+R2vZZu; incap_ses_713_621087=IuVrdOb1HwK0pTS8ExblCT8B6GAAAAAAWyswWx7wzWve4j23+Nsp4w=='
                ),
                ));
        
                $response = curl_exec($curl);
                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
                curl_close($curl);
                
                $response=json_decode($response);

                if ($httpcode>299) {
                    $error = Validator::make([], []);
                    $error->getMessageBag()->add('gateway_error', $response->response->response_desc);
                    return redirect()->back()->withErrors($error);
                }

                $customer_token=$response->customer_token;
            } catch (\Throwable $th) {
                throw $th;
            }
        }else{
            $customer_token = $this->forte->client->gateway_tokens[0]->gateway_customer_reference;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => $this->forte_base_uri.'organizations/'.$this->forte_organization_id.'/locations/'.$this->forte_location_id.'/customers/'.$customer_token.'/paymethods',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>'{
            "notes":"'.$request->account_holder_name.' echeck",
            "echeck": {
                "account_holder": "'.$request->account_holder_name.'",
                "account_number":"'.$request->account_number.'",
                "routing_number":"'.$request->routing_number.'",
                "account_type":"checking"
            }
        }',
        CURLOPT_HTTPHEADER => array(
            'X-Forte-Auth-Organization-Id: '.$this->forte_organization_id,
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode($this->forte_api_access_id.':'.$this->forte_secure_key),
            'Cookie: visid_incap_621087=QJCccwHeTHinK5DnAeQIuXPk5mAAAAAAQUIPAAAAAAATABmm7IZkHhUi85sN+UaS; nlbi_621087=tVVcSY5O+xzIMhyvR1efXgAAAABn4GsrsejFXewG9LEvz7cm; incap_ses_9153_621087=wAileyRCBU3lBWqsNP0Ff80/6GAAAAAASCPsRmBm9ygyrCA0iBX3kg==; incap_ses_9210_621087=OHvJaqfG9Cc+r/0GZX7Qf10a6WAAAAAA1CWMfnTjC/4Y/4bz/HTgBg==; incap_ses_713_621087=Lu/yR4IM2iokOlO8ExblCSWB6WAAAAAANBLUy0jRk/4YatHkXIajvA=='
        ),
        ));

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $response=json_decode($response);

        if ($httpcode>299) {
            $error = Validator::make([], []);
            $error->getMessageBag()->add('gateway_error', $response->response->response_desc);
            return redirect()->back()->withErrors($error);
        }

        $payment_meta = new \stdClass;
        // $payment_meta->brand = (string)sprintf('%s (%s)', $request->bank_name, ctrans('texts.ach'));
        $payment_meta->brand = (string)ctrans('texts.ach');
        $payment_meta->last4 = (string)$response->echeck->last_4_account_number;
        $payment_meta->exp_year = '-';
        $payment_meta->type = GatewayType::BANK_TRANSFER;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $response->paymethod_token,
            'payment_method_id' => $request->gateway_type_id,
        ];

        $this->forte->storeGatewayToken($data, ['gateway_customer_reference' => $customer_token]);

        return redirect()->route('client.payment_methods.index');
    }

    public function paymentView(array $data)
    {
        $this->forte->payment_hash->data = array_merge((array) $this->forte->payment_hash->data, $data);
        $this->forte->payment_hash->save();

        $data['gateway'] = $this;
        $data['system_amount_with_fee'] = $data['amount_with_fee'];
        $data['fee_percent'] = $this->forte->company_gateway->fees_and_limits->{GatewayType::BANK_TRANSFER}->fee_percent;
        $data['total']['fee_total'] = $data['total']['invoice_totals'] * $data['fee_percent'] / 100;
        $data['total']['amount_with_fee'] = $data['total']['fee_total'] + $data['total']['invoice_totals'];
        $data['amount_with_fee'] = $data['total']['amount_with_fee'];
        return render('gateways.forte.ach.pay', $data);
    }

    public function paymentResponse($request)
    {
        $data=$request;

        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
            CURLOPT_URL => $this->forte_base_uri.'organizations/'.$this->forte_organization_id.'/locations/'.$this->forte_location_id.'/transactions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS =>'{
                "action":"sale",
                "authorization_amount": '.$data->amount_with_fee.',
                "paymethod_token": "'.$data->payment_token.'",
                "echeck":{
                    "sec_code":"PPD",
                },
                "billing_address":{
                    "first_name": "'.auth()->user()->client->name.'",
                    "last_name": "'.auth()->user()->client->name.'"
                }
            }',
            CURLOPT_HTTPHEADER => array(
                'X-Forte-Auth-Organization-Id: '.$this->forte_organization_id,
                'Content-Type: application/json',
                'Authorization: Basic '.base64_encode($this->forte_api_access_id.':'.$this->forte_secure_key),
                'Cookie: visid_incap_621087=u18+3REYR/iISgzZxOF5s2ODW2IAAAAAQUIPAAAAAADuGqKgECQLS81FcSDrmhGe; nlbi_621087=YHngadhJ2VU+yr7/R1efXgAAAAD3mQyhqmnLls8PRu4iN58G; incap_ses_1136_621087=CVdrXUdhIlm9WJNDieLDD4QVXGIAAAAAvBwvkUcwhM0+OwvdPm2stg=='
            ),
            ));

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            $response=json_decode($response);
        } catch (\Throwable $th) {
            throw $th;
        }
        
        if ($httpcode>299) {
            $error = Validator::make([], []);
            $error->getMessageBag()->add('gateway_error', $response->response->response_desc);
            return redirect('client/invoices')->withErrors($error);
        }

        $data['gateway_type_id']=GatewayType::CREDIT_CARD;
        $data['amount']=$request->system_amount_with_fee;
        $data['payment_type']=GatewayType::CREDIT_CARD;
        $data['transaction_reference']=$response->transaction_id;

        $payment=$this->forte->createPayment($data, Payment::STATUS_COMPLETED);
        return redirect('client/invoices')->withSuccess('Invoice paid.');
    }
}
