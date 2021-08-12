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

namespace App\PaymentDrivers\Eway;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\EwayPaymentDriver;
use App\PaymentDrivers\Eway\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CreditCard
{

    public $eway_driver;

    public function __construct(EwayPaymentDriver $eway_driver)
    {
        $this->eway_driver = $eway_driver;
    }

    public function authorizeView($data)
    {

        $data['gateway'] = $this->eway_driver;
        $data['api_key'] = $this->eway_driver->company_gateway->getConfigField('apiKey');
        $data['public_api_key'] = $this->eway_driver->company_gateway->getConfigField('publicApiKey');

        return render('gateways.eway.authorize', $data);

    }

    /*
    Eway\Rapid\Model\Response\CreateCustomerResponse {#2374 ▼
  #fillable: array:16 [▶]
  #errors: []
  #attributes: array:11 [▼
    "AuthorisationCode" => null
    "ResponseCode" => "00"
    "ResponseMessage" => "A2000"
    "TransactionID" => null
    "TransactionStatus" => false
    "TransactionType" => "MOTO"
    "BeagleScore" => null
    "Verification" => Eway\Rapid\Model\Verification {#2553 ▼
      #fillable: array:5 [▶]
      #attributes: array:5 [▶]
    }
    "Customer" => Eway\Rapid\Model\Customer {#2504 ▼
      #fillable: array:38 [▶]
      #attributes: array:20 [▼
        "CardDetails" => Eway\Rapid\Model\CardDetails {#2455 ▼
          #fillable: array:8 [▶]
          #attributes: array:7 [▼
            "Number" => "411111XXXXXX1111"
            "Name" => "Joey Diaz"
            "ExpiryMonth" => "10"
            "ExpiryYear" => "23"
            "StartMonth" => null
            "StartYear" => null
            "IssueNumber" => null
          ]
        }
        "TokenCustomerID" => 917047257342
        "Reference" => "A12345"
        "Title" => "Mr."
        "FirstName" => "John"
        "LastName" => "Smith"
        "CompanyName" => "Demo Shop 123"
        "JobDescription" => "PHP Developer"
        "Street1" => "Level 5"
        "Street2" => "369 Queen Street"
        "City" => "Sydney"
        "State" => "NSW"
        "PostalCode" => "2000"
        "Country" => "au"
        "Email" => "demo@example.org"
        "Phone" => "09 889 0986"
        "Mobile" => "09 889 6542"
        "Comments" => ""
        "Fax" => ""
        "Url" => "http://www.ewaypayments.com"
      ]
    }
    "Payment" => Eway\Rapid\Model\Payment {#2564 ▼
      #fillable: array:5 [▶]
      #attributes: array:5 [▼
        "TotalAmount" => 0
        "InvoiceNumber" => ""
        "InvoiceDescription" => ""
        "InvoiceReference" => ""
        "CurrencyCode" => "AUD"
      ]
    }
    "Errors" => null
  ]
}
     */

    public function authorizeResponse($request)
    {

        $transaction = [
            'Reference' => $this->eway_driver->client->number,
            'Title' => '',
            'FirstName' => $this->eway_driver->client->contacts()->first()->present()->last_name(),
            'LastName' => $this->eway_driver->client->contacts()->first()->present()->first_name(),
            'CompanyName' => $this->eway_driver->client->name,
            'Street1' => $this->eway_driver->client->address1,
            'Street2' => $this->eway_driver->client->address2,
            'City' => $this->eway_driver->client->city,
            'State' => $this->eway_driver->client->state,
            'PostalCode' => $this->eway_driver->client->postal_code,
            'Country' => $this->eway_driver->client->country->iso_3166_2,
            'Phone' => $this->eway_driver->client->phone,
            'Email' => $this->eway_driver->client->contacts()->first()->email,
            "Url" => $this->eway_driver->client->website,
            // 'Payment' => [
            //     'TotalAmount' => 0,
            // ],
            // 'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
            'Method' => \Eway\Rapid\Enum\PaymentMethod::CREATE_TOKEN_CUSTOMER,
            'SecuredCardData' => $request->input('securefieldcode'),
        ];

        $response = $this->eway_driver->init()->eway->createCustomer(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

        $response_status = ErrorCode::getStatus($response->ResponseMessage);

        if(!$response_status['success'])
          throw new PaymentFailed($response_status['message'], 400);

        //success
        $cgt = [];
        $cgt['token'] = $response->Customer->TokenCustomerID;
        $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

        $payment_meta = new \stdClass;
        $payment_meta->exp_month = $response->Customer->CardDetails->ExpiryMonth;
        $payment_meta->exp_year = $response->Customer->CardDetails->ExpiryYear;
        $payment_meta->brand = 'CC';
        $payment_meta->last4 = substr($response->Customer->CardDetails->Number, -4);;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $cgt['payment_meta'] = $payment_meta;

        $token = $this->eway_driver->storeGatewayToken($cgt, []);

        return redirect()->route('client.payment_methods.index');

    }

    public function paymentView($data)
    {
    
        $data['gateway'] = $this->eway_driver;
        $data['public_api_key'] = $this->eway_driver->company_gateway->getConfigField('publicApiKey');

        return render('gateways.eway.pay', $data);

    }


    public function paymentResponse($request)
    {
        
    }
}