<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Factory\PaymentFactory;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Http\Requests\Request;
use App\Jobs\Util\SystemLogger;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\Stripe\Alipay;
use App\PaymentDrivers\Stripe\Charge;
use App\PaymentDrivers\Stripe\CreditCard;
use App\PaymentDrivers\Stripe\SEPA;
use App\PaymentDrivers\Stripe\SOFORT;
use App\PaymentDrivers\Stripe\Utilities;
use App\Utils\Traits\MakesHash;
use Exception;
use Illuminate\Support\Carbon;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripeConnectPaymentDriver extends StripePaymentDriver
{
    public function __construct(CompanyGateway $company_gateway, Client $client = null, $invitation = false)
    {
        parent::__construct($company_gateway, $client, $invitation);

        $this->stripe_connect = true;
    }
}
