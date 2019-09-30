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
use Omnipay\Common\Item;

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
        $this->offsitePurchase($this->paymentDetails($data), $this->paymentItems($data));
    }

    public function processPaymentResponse($request)
    {

        $response = $this->completePurchase($request->all());

        $paymentRef = $response->getTransactionReference() ?: $transRef;

        if ($response->isCancelled()) {
            return false;
        } elseif (! $response->isSuccessful()) {
            throw new Exception($response->getMessage());
        }

        dd($response);
    }

    protected function paymentDetails($input)
    {
        $data = parent::paymentDetails($input);

        $data['amount'] = $input['amount_with_fee'];
        $data['returnUrl'] = $this->buildReturnUrl($input);
        $data['cancelUrl'] = $this->buildCancelUrl($input);
        $data['description'] = $this->buildDescription($input);
        $data['transactionId'] = $this->buildTransactionId($input);

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option
        $data['transactionId'] = $data['transactionId'] . '-' . time();

        return $data;
    }

    private function buildReturnUrl($input)
    {
        $url = $this->client->company->domain . "client/payments/process/response";
        $url .= "?company_gateway_id={$this->company_gateway->id}&gateway_type_id=".GatewayType::PAYPAL;
        $url .= "&hashed_ids=" . implode(",", $input['hashed_ids']); 
        $url .= "&amount=".$input['amount'];
        $url .= "&fee=".$input['fee'];

        return $url;
    }

    private function buildCancelUrl($input)
    {
        $url = $this->client->company->domain . '/client/invoices';

        return $url;
    }

    private function buildDescription($input)
    {
        $invoice_numbers = "";
        
        foreach($input['invoices'] as $invoice)
        {
            $invoice_numbers .= $invoice->invoice_number." ";
        }

        return ctrans('texts.invoice_number'). ": {$invoice_numbers}";

    }

    private function buildTransactionId($input)
    {
        return implode(",", $input['hashed_ids']);    
    }

    private function paymentItems($input) : array
    {

        $items = [];
        $total = 0;

        foreach ($input['invoices'] as $invoice) 
        {
            foreach($invoice->line_items as $invoiceItem)
            {
                // Some gateways require quantity is an integer
                if (floatval($invoiceItem->quantity) != intval($invoiceItem->quantity)) {
                    return null;
                }

                $item = new Item([
                    'name' => $invoiceItem->product_key,
                    'description' => substr($invoiceItem->notes, 0, 100),
                    'price' => $invoiceItem->cost,
                    'quantity' => $invoiceItem->quantity,
                ]);

                $items[] = $item;

                $total += $invoiceItem->cost * $invoiceItem->quantity;
            }
        }

        if ($total != $input['amount_with_fee']) {
            $item = new Item([
                'name' => trans('texts.taxes_and_fees'),
                'description' => '',
                'price' => $input['amount_with_fee'] - $total,
                'quantity' => 1,
            ]);

            $items[] = $item;
        }

        return $items;
    }
    
}