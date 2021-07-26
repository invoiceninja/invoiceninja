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

    public function authorizeResponse($request)
    {

        $this->eway_driver->init();

    $transaction = [
            'Reference' => 'A12345',
            'Title' => 'Mr.',
            'FirstName' => 'John',
            'LastName' => 'Smith',
            'CompanyName' => 'Demo Shop 123',
            'JobDescription' => 'PHP Developer',
            'Street1' => 'Level 5',
            'Street2' => '369 Queen Street',
            'City' => 'Sydney',
            'State' => 'NSW',
            'PostalCode' => '2000',
            'Country' => 'au',
            'Phone' => '09 889 0986',
            'Mobile' => '09 889 6542',
            'Email' => 'demo@example.org',
            "Url" => "http://www.ewaypayments.com",
            'Payment' => [
                'TotalAmount' => 0,
            ],
            'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
            'Method' => \Eway\Rapid\Enum\PaymentMethod::CREATE_TOKEN_CUSTOMER,
            'SecuredCardData' => $request->input('SecuredCardData'),
        ];

        $response = $this->eway_driver->init()->eway->createCustomer(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

dd($response);
    }

    public function paymentView($data)
    {
    
    }

    public function processPaymentResponse($request)
    {
        
    }
}