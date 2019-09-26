<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class PayPalExpressPaymentDriver extends BasePaymentDriver
{
    
  	use MakesHash;

    protected $refundable = false;

    protected $token_billing = false;

    protected $can_authorise_credit_card = false;

    protected $customer_reference = '';


    public function gatewayTypes()
    {
        return [
            GatewayType::PAYPAL,
        ];
    }

    /**
     * Processes the payment with this gateway
     *             
     * @var $data['invoices']
     * @var $data['amount']
     * @var $data['fee']
     * @var $data['amount_with_fee']
     * @var $data['token']
     * @var $data['payment_method_id']
     * @var $data['hashed_ids']
     * 
     * @param  array  $data variables required to build payment page
     * @return view   Gateway and payment method specific view
     */
    public function processPaymentView(array $data)
    {

    }

    public function processPaymentResponse($request)
    {

    }

    protected function paymentDetails()
    {
        $data = parent::paymentDetails();

        $data['amount'] = $invoice->getRequestedAmount();
        $data['returnUrl'] = $completeUrl;
        $data['cancelUrl'] = $this->invitation->getLink();
        $data['description'] = trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}";
        $data['transactionId'] = $invoice->invoice_number;

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option
        $data['transactionId'] = $data['transactionId'] . '-' . time();

        return $data;
    }
}