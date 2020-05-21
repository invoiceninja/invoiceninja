<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\SystemLogTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Omnipay\Omnipay;

/**
 * Class BasePaymentDriver
 * @package App\PaymentDrivers
 *
 *  Minimum dataset required for payment gateways
 *
 *  $data = [
        'amount' => $invoice->getRequestedAmount(),
        'currency' => $invoice->getCurrencyCode(),
        'returnUrl' => $completeUrl,
        'cancelUrl' => $this->invitation->getLink(),
        'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->number}",
        'transactionId' => $invoice->number,
        'transactionType' => 'Purchase',
        'clientIp' => Request::getClientIp(),
    ];

 */
class CheckoutPaymentDriver extends BasePaymentDriver
{
    use SystemLogTrait;

    /* The company gateway instance*/
    protected $company_gateway;

    /* The Omnipay payment driver instance*/
    protected $gateway;

    /* The Invitation */
    protected $invitation;

    /* Gateway capabilities */
    protected $refundable = true;

    /* Token billing */
    protected $token_billing = true;

    /* Authorise payment methods */
    protected $can_authorise_credit_card = true;

    public function getPublishableKey() :?string
    {
        // @todo: Doesn't return right property key.
        // return $this->company_gateway->getPublishableKey();

        return 'pk_test_70f73945-07c0-4ec3-8f10-35250c747542';
    }

    public function getCustomerEmail(): string
    {
        return $this->getContact()->email;
    }

    public function viewForType($gateway_type_id)
    {
        switch ($gateway_type_id) {
            case GatewayType::CREDIT_CARD:
                return 'gateways.checkout.credit_card';
                break;
            case GatewayType::TOKEN:
                break;

            default:
                break;
        }
    }

    public function formatAmount($amount, $currency)
    {
        // Reference: https://github.com/invoiceninja/invoiceninja/blob/master/app/Ninja/PaymentDrivers/CheckoutComPaymentDriver.php

        if ($currency == 'BHD') {
            return $amount / 10;
        }

        if ($currency == 'KWD') {
            return $amount * 10;
        }

        return $amount;
    }

    public function processPaymentView(array $data)
    {
        $data['gateway'] = $this->gateway();
        $data['currency'] = $this->getContact()->client->getCurrencyCode();
        $data['amount'] = $this->formatAmount($data['amount'], $data['currency']);

        return render($this->viewForType($data['payment_method_id']), $data);
    }

    public function processPaymentResponse($request) 
    {
        // ..
	}
}