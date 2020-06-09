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

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\Utilities;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;
use Stripe\Stripe;

class AuthorizeNetAIMPaymentDriver extends BasePaymentDriver
{
    use MakesHash;

    protected $refundable = true;

    protected $token_billing = true;

    protected $can_authorise_credit_card = true;

    protected $transactionReferenceParam = 'refId';

    /**
     * Returns the gateway types
     */
    public function gatewayTypes() :array
    {
        $types = [
            GatewayType::CREDIT_CARD,
        ];

        return $types;
    }

    public function viewForType($gateway_type_id)
    {
        switch ($gateway_type_id) {
            case GatewayType::CREDIT_CARD:
            case GatewayType::TOKEN:
                return 'gateways.authorize.credit_card';
                break;

            default:
                break;
        }
    }

    public function getLoginId()
    {
      return $this->company_gateway->getConfigField('apiLoginId');
    }

    public function getTransactionKey()
    {
      return $this->company_gateway->getConfigField('transactionKey');
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->gateway;
        
        return render($this->viewForType(GatewayType::CREDIT_CARD), $data);
    }

    public function authorizeCreditCardResponse($request)
    {

      $request = $gateway->authorize(
          [
              'amount' => 0,
              'opaqueDataDescriptor' => $request->input('dataDescriptor'),
              'opaqueDataValue' => $request->input('dataValue'),
          ]
      );

        $response = $request->send();
        $data = $response->getData();

        info($data);

        $data['paymentProfile']['customerProfileId'];
        $data['paymentProfile']['customerPaymentProfileId'];

    }



}
