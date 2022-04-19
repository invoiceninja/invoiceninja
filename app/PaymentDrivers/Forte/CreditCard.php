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

use App\Models\Payment;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use App\PaymentDrivers\FortePaymentDriver;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class CreditCard
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

        $this->forte_base_uri = $this->forte->company_gateway->getConfigField('baseUri');
        $this->forte_api_access_id = $this->forte->company_gateway->getConfigField('apiAccessId');
        $this->forte_secure_key = $this->forte->company_gateway->getConfigField('secureKey');
        $this->forte_auth_organization_id = $this->forte->company_gateway->getConfigField('authOrganizationId');
        $this->forte_organization_id = $this->forte->company_gateway->getConfigField('organizationId');
        $this->forte_location_id = $this->forte->company_gateway->getConfigField('locationId');
    }

    public function authorizeView(array $data)
    {
        return render('gateways.forte.credit_card.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $customer_token = null;
        $request->validate([
            'card_number'=>'required',
            'card_holders_name'=>'required|string',
            'expiry_month'=>'required',
            'expiry_year'=>'required',
            'cvc'=>'required',
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
                    "first_name": "'.$this->forte->client->present()->name().'",
                    "last_name": "'.$this->forte->client->present()->name().'",
                    "company_name": "'.$this->forte->client->present()->name().'",
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
            "notes":"'.$request->card_holders_name.' Card",
            "card": {
                "name_on_card":"'.$request->card_holders_name.'",
                "card_type":"'.$request->card_type.'",
                "account_number":"'.str_replace(' ', '', $request->card_number).'",
                "expire_month":'.$request->expiry_month.',
                "expire_year":20'.$request->expiry_year.',
                "card_verification_value": "'.$request->cvc.'"
            }   
        }',
        CURLOPT_HTTPHEADER => array(
            'X-Forte-Auth-Organization-Id: '.$this->forte_organization_id,
            'Content-Type: application/json',
            'Authorization: Basic '.base64_encode($this->forte_api_access_id.':'.$this->forte_secure_key),
            'Cookie: visid_incap_621087=QJCccwHeTHinK5DnAeQIuXPk5mAAAAAAQUIPAAAAAAATABmm7IZkHhUi85sN+UaS'
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
        $payment_meta->exp_month = (string) $response->card->expire_month;
        $payment_meta->exp_year = (string) $response->card->expire_year;
        $payment_meta->brand = (string) $response->card->card_type;
        $payment_meta->last4 = (string) $response->card->last_4_account_number;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $response->paymethod_token,
            'payment_method_id' => $request->payment_method_id,
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
        $data['fee_percent'] = $this->forte->company_gateway->fees_and_limits->{GatewayType::CREDIT_CARD}->fee_percent;
        $data['total']['fee_total'] = $data['total']['invoice_totals'] * $data['fee_percent'] / 100;
        $data['total']['amount_with_fee'] = $data['total']['fee_total'] + $data['total']['invoice_totals'];
        $data['amount_with_fee'] = $data['total']['amount_with_fee'];
        return render('gateways.forte.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
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
                "service_fee_amount": '.$data->fee_total.',
                "paymethod_token": "'.$data->payment_token.'",
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
        $data['payment_type']=PaymentType::parseCardType(strtolower($request->card_brand)) ?: PaymentType::CREDIT_CARD_OTHER;
        $data['transaction_reference']=$response->transaction_id;

        $payment=$this->forte->createPayment($data, Payment::STATUS_COMPLETED);
        return redirect('client/invoices')->withSuccess('Invoice paid.');
    }
}
