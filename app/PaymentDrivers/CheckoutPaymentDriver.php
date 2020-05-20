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

    public function createTransactionToken($amount)
    {
        // if ($this->invoice()->getCurrencyCode() == 'BHD') {
        //     $amount = $this->invoice()->getRequestedAmount() / 10;
        // } elseif ($this->invoice()->getCurrencyCode() == 'KWD') {
        //     $amount = $this->invoice()->getRequestedAmount() * 10;
        // } elseif ($this->invoice()->getCurrencyCode() == 'OMR') {
        //     $amount = $this->invoice()->getRequestedAmount();
        // } else
        //     $amount = $this->invoice()->getRequestedAmount();

        $response = $this->gateway()->purchase([
            'amount' => $amount,
            'currency' => $this->client->getCurrencyCode(),
        ],[])->send();

        if ($response->isRedirect()) {
            $token = $response->getTransactionReference();

            session()->flash('transaction_reference', $token);


            // On each request, session()->flash() || sesion('', value) || session[name] ||session->flash(key, value)

            return $token;
        }

        return false;
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
    
    /**
     *         
     *  $data = [
            'invoices' => $invoices,
            'amount' => $amount,
            'fee' => $gateway->calcGatewayFee($amount),
            'amount_with_fee' => $amount + $gateway->calcGatewayFee($amount),
            'token' => auth()->user()->client->gateway_token($gateway->id, $payment_method_id),
            'payment_method_id' => $payment_method_id,
            'hashed_ids' => explode(",", request()->input('hashed_ids')),
        ];
     */
    public function processPaymentView(array $data)
    {
        $data['gateway'] = $this->gateway();

        return render($this->viewForType($data['payment_method_id']), $data);
    }



    public function processPaymentResponse($request) 
    {
        $data['token'] = session('transaction_reference');

        $this->completeOffsitePurchase($data);

	}
}