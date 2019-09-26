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

    public function processPaymentView(array $data)
    {

    }

    public function processPaymentResponse($request)
    {

    }

    protected function paymentDetails($paymentMethod = false)
    {
        $data = parent::paymentDetails();

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option
        $data['transactionId'] = $data['transactionId'] . '-' . time();

        return $data;
    }
}