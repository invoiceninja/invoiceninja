<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Events\Payment\PaymentWasCreated;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Exception;
use Omnipay\Common\Item;
use stdClass;

class PayPalExpressPaymentDriver extends BasePaymentDriver
{
    use MakesHash;

    public $payment_hash;

    public $required_fields = [];

    protected $refundable = true;

    protected $token_billing = false;

    protected $can_authorise_credit_card = false;

    protected $customer_reference = '';

    public function setPaymentMethod($payment_method_id = null)
    {
        return $this;
    }

    public function gatewayTypes()
    {
        return [
            GatewayType::PAYPAL,
        ];
    }

    const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    public function checkRequirements()
    {
        if ($this->company_gateway->require_billing_address) {
            if ($this->checkRequiredResource(auth()->user('contact')->client->address1)) {
                $this->required_fields[] = 'billing_address1';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->address2)) {
                $this->required_fields[] = 'billing_address2';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->city)) {
                $this->required_fields[] = 'billing_city';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->state)) {
                $this->required_fields[] = 'billing_state';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->postal_code)) {
                $this->required_fields[] = 'billing_postal_code';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->country_id)) {
                $this->required_fields[] = 'billing_country';
            }
        }

        if ($this->company_gateway->require_shipping_address) {
            if ($this->checkRequiredResource(auth()->user('contact')->client->shipping_address1)) {
                $this->required_fields[] = 'shipping_address1';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->shipping_address2)) {
                $this->required_fields[] = 'shipping_address2';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->shipping_city)) {
                $this->required_fields[] = 'shipping_city';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->shipping_state)) {
                $this->required_fields[] = 'shipping_state';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->shipping_postal_code)) {
                $this->required_fields[] = 'shipping_postal_code';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->shipping_country_id)) {
                $this->required_fields[] = 'shipping_country';
            }
        }

        if ($this->company_gateway->require_client_name) {
            if ($this->checkRequiredResource(auth()->user('contact')->client->name)) {
                $this->required_fields[] = 'name';
            }
        }

        if ($this->company_gateway->require_client_phone) {
            if ($this->checkRequiredResource(auth()->user('contact')->client->phone)) {
                $this->required_fields[] = 'phone';
            }
        }

        if ($this->company_gateway->require_contact_email) {
            if ($this->checkRequiredResource(auth()->user('contact')->email)) {
                $this->required_fields[] = 'contact_email';
            }
        }

        if ($this->company_gateway->require_contact_name) {
            if ($this->checkRequiredResource(auth()->user('contact')->first_name)) {
                $this->required_fields[] = 'contact_first_name';
            }

            if ($this->checkRequiredResource(auth()->user('contact')->last_name)) {
                $this->required_fields[] = 'contact_last_name';
            }
        }

        if ($this->company_gateway->require_postal_code) {
            // In case "require_postal_code" is true, we don't need billing address.

            foreach ($this->required_fields as $position => $field) {
                if (Str::startsWith($field, 'billing')) {
                    unset($this->required_fields[$position]);
                }
            }

            if ($this->checkRequiredResource(auth()->user('contact')->client->postal_code)) {
                $this->required_fields[] = 'postal_code';
            }
        }

        return $this;
    }

    /**
     * Wrapper method for checking if resource is good.
     *
     * @param mixed $resource
     * @return bool
     */
    public function checkRequiredResource($resource): bool
    {
        if (is_null($resource) || empty($resource)) {
            return true;
        }

        return false;
    }

    /**
     * Processes the payment with this gateway.
     *
     *
     * @param array $data variables required to build payment page
     * @return void Gateway and payment method specific view
     * @throws Exception
     */
    public function processPaymentView(array $data)
    {
        if (count($this->required_fields) > 0) {
            return redirect()
                ->route('client.profile.edit', ['client_contact' => auth()->user()->hashed_id])
                ->with('missing_required_fields', $this->required_fields);
        }

        $response = $this->purchase($this->paymentDetails($data), $this->paymentItems($data));

        if ($response->isRedirect()) {
            // redirect to offsite payment gateway
            $response->redirect();
        } elseif ($response->isSuccessful()) {
            // payment was successful: update database
            /* for this driver this method wont be hit*/
        } else {
            // payment failed: display message to customer

            SystemLogger::dispatch(
                [
                    'server_response' => $response->getData(),
                    'data' => $data,
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );

            throw new Exception('Error Processing Payment', 1);
        }
    }

    public function setPaymentHash(PaymentHash $payment_hash)
    {
        $this->payment_hash = $payment_hash;

        return $this;
    }

    public function processPaymentResponse($request)
    {
        if (count($this->required_fields) > 0) {
            return redirect()
                ->route('client.profile.edit', ['client_contact' => auth()->user()->hashed_id])
                ->with('missing_required_fields', $this->required_fields);
        }
        
        $response = $this->completePurchase($request->all());

        $transaction_reference = $response->getTransactionReference() ?: $request->input('token');

        if ($response->isCancelled()) {
            return redirect()->route('client.invoices.index')->with('warning', ctrans('texts.status_cancelled'));
        } elseif ($response->isSuccessful()) {
            SystemLogger::dispatch(
                [
                    'server_response' => $response->getData(),
                    'data' => $request->all(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );
        } elseif (! $response->isSuccessful()) {
            PaymentFailureMailer::dispatch($this->client, $response->getMessage, $this->client->company, $response['PAYMENTINFO_0_AMT']);

            SystemLogger::dispatch(
                [
                    'data' => $request->all(),
                    'server_response' => $response->getData(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client
            );

            throw new Exception($response->getMessage());
        }

        $payment = $this->createPayment($response->getData());
        $payment_hash = PaymentHash::whereRaw('BINARY `hash`= ?', [$request->input('payment_hash')])->firstOrFail();

        $payment_hash->payment_id = $payment->id;
        $payment_hash->save();

        $this->attachInvoices($payment, $payment_hash);
        $payment->service()->updateInvoicePayment($payment_hash);

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    protected function paymentDetails($input): array
    {
        $data = parent::paymentDetails($input);

        $data['amount'] = $input['amount_with_fee'];
        $data['returnUrl'] = $this->buildReturnUrl($input);
        $data['cancelUrl'] = $this->buildCancelUrl($input);
        $data['description'] = $this->buildDescription($input);
        $data['transactionId'] = $this->buildTransactionId($input);

        $data['ButtonSource'] = 'InvoiceNinja_SP';
        $data['solutionType'] = 'Sole'; // show 'Pay with credit card' option
        $data['transactionId'] = $data['transactionId'].'-'.time();

        return $data;
    }

    private function buildReturnUrl($input): string
    {
        return route('client.payments.response', [
            'company_gateway_id' => $this->company_gateway->id,
            'payment_hash' => $this->payment_hash->hash,
            'payment_method_id' => GatewayType::PAYPAL,
        ]);
    }

    private function buildCancelUrl($input): string
    {
        $url = $this->client->company->domain().'/client/invoices';

        return $url;
    }

    private function buildDescription($input): string
    {
        $invoice_numbers = '';

        foreach ($input['invoices'] as $invoice) {
            $invoice_numbers .= $invoice->number.' ';
        }

        return ctrans('texts.invoice_number').": {$invoice_numbers}";
    }

    private function buildTransactionId($input): string
    {
        return implode(',', $input['hashed_ids']);
    }

    private function paymentItems($input): array
    {
        $items = [];
        $total = 0;

        foreach ($input['invoices'] as $invoice) {
            foreach ($invoice->line_items as $invoiceItem) {
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

    public function createPayment($data, $status = Payment::STATUS_COMPLETED): Payment
    {
        $payment_meta = new stdClass;
        $payment_meta->exp_month = 'xx';
        $payment_meta->exp_year = 'xx';
        $payment_meta->brand = 'PayPal';
        $payment_meta->last4 = 'xxxx';
        $payment_meta->type = GatewayType::PAYPAL;

        $payment = parent::createPayment($data, $status);

        $client_contact = $this->getContact();
        $client_contact_id = $client_contact ? $client_contact->id : null;

        $payment->amount = $data['PAYMENTINFO_0_AMT'];
        $payment->type_id = PaymentType::PAYPAL;
        $payment->transaction_reference = $data['PAYMENTINFO_0_TRANSACTIONID'];
        $payment->client_contact_id = $client_contact_id;
        $payment->meta = $payment_meta;
        $payment->save();

        return $payment;
    }

    public function refund(Payment $payment, $amount)
    {
        $this->gateway();

        $response = $this->gateway
            ->refund(['transactionReference' => $payment->transaction_reference, 'amount' => $amount])
            ->send();

        if ($response->isSuccessful()) {
            SystemLogger::dispatch([
                'server_response' => $response->getMessage(), 'data' => request()->all(),
            ], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_PAYPAL, $this->client);

            return [
                'transaction_reference' => $response->getData()['REFUNDTRANSACTIONID'],
                'transaction_response' => json_encode($response->getData()),
                'success' => true,
                'description' => $response->getData()['ACK'],
                'code' => $response->getCode(),
            ];
        }

        SystemLogger::dispatch([
            'server_response' => $response->getMessage(), 'data' => request()->all(),
        ], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_PAYPAL, $this->client);

        return [
            'transaction_reference' => $response->getData()['CORRELATIONID'],
            'transaction_response' => json_encode($response->getData()),
            'success' => false,
            'description' => $response->getData()['L_LONGMESSAGE0'],
            'code' => $response->getData()['L_ERRORCODE0'],
        ];
    }

    /**
     * Detach payment method from PayPal.
     *
     * @param ClientGatewayToken $token
     * @return void
     */
    public function detach(ClientGatewayToken $token)
    {
        // PayPal doesn't support this feature.
    }
}
