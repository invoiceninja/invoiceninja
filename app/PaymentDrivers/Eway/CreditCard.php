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
        $data['public_api_key'] = 'epk-8C1675E6-8E07-4C86-8946-71B3DE390F44';

        return render('gateways.eway.authorize', $data);

    }

    public function authorizeRequest($request)
    {

        $transaction = [
            'Title' => 'Mr.',
            'FirstName' => 'John',
            'LastName' => 'Smith',
            'Country' => 'au',
            'Payment' => [
                'TotalAmount' => 0,
            ],
            'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
            'Method' => \Eway\Rapid\Enum\PaymentMethod::CREATE_TOKEN_CUSTOMER,
            'SecuredCardData' => $request->input('SecuredCardData'),
        ];

        $response = $client->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

    }

    public function paymentView($data)
    {
    
    }

    public function processPaymentResponse($request)
    {
        
    }
}