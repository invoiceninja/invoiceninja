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
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SystemLogTrait;
use Illuminate\Support\Carbon;
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
class BasePaymentDriver
{
    use SystemLogTrait;
    use MakesHash;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Omnipay payment driver instance*/
    protected $gateway;

    /* The Invitation */
    protected $invitation;

    /* Gateway capabilities */
    protected $refundable = false;

    /* Token billing */
    protected $token_billing = false;

    /* Authorise payment methods */
    protected $can_authorise_credit_card = false;


    public function __construct(CompanyGateway $company_gateway, Client $client, $invitation = false)
    {
        $this->company_gateway = $company_gateway;

        $this->invitation = $invitation;

        $this->client = $client;
    }

    /**
     * Returns the Omnipay driver
     * @return object Omnipay initialized object
     */
    protected function gateway()
    {
        $this->gateway = Omnipay::create($this->company_gateway->gateway->provider);
        $this->gateway->initialize((array) $this->company_gateway->getConfig());

        return $this;
    }

    /**
     * Return the configuration fields for the
     * Gatway
     * @return array The configuration fields
     */
    public function getFields()
    {
        return $this->gateway->getDefaultParameters();
    }

    /**
     * Returns the default gateway type
     */
    public function gatewayTypes()
    {
        return [
            GatewayType::CREDIT_CARD,
        ];
    }

    public function getCompanyGatewayId(): int
    {
        return $this->company_gateway->id;
    }
    /**
     * Returns whether refunds are possible with the gateway
     * @return boolean TRUE|FALSE
     */
    public function getRefundable(): bool
    {
        return $this->refundable;
    }

    /**
     * Returns whether token billing is possible with the gateway
     * @return boolean TRUE|FALSE
     */
    public function getTokenBilling(): bool
    {
        return $this->token_billing;
    }

    /**
     * Returns whether gateway can
     * authorise and credit card.
     * @return [type] [description]
     */
    public function canAuthoriseCreditCard(): bool
    {
        return $this->can_authorise_credit_card;
    }

    /**
     * Refunds a given payment
     * @return void
     */
    public function refundPayment($payment, $amount = 0)
    {
        if ($amount) {
            $amount = min($amount, $payment->getCompletedAmount());
        } else {
            $amount = $payment->getCompletedAmount();
        }

        if ($payment->is_deleted || !$amount) {
            return false;
        }

        if ($payment->type_id == Payment::TYPE_CREDIT_CARD) {
            return $payment->recordRefund($amount);
        }

        $details = $this->refundDetails($payment, $amount);
        $response = $this->gateway()->refund($details)->send();

        if ($response->isSuccessful()) {
            return $payment->recordRefund($amount);
        } elseif ($this->attemptVoidPayment($response, $payment, $amount)) {
            $details = ['transactionReference' => $payment->transaction_reference];
            $response = $this->gateway->void($details)->send();
            if ($response->isSuccessful()) {
                return $payment->markVoided();
            }
        }

        return false;
    }

    protected function attemptVoidPayment($response, $payment, $amount)
    {
        // Partial refund not allowed for unsettled transactions
        return $amount == $payment->amount;
    }

    public function authorizeCreditCardView(array $data)
    {
    }

    public function authorizeCreditCardResponse($request)
    {
    }

    public function processPaymentView(array $data)
    {
    }

    public function processPaymentResponse($request)
    {
    }

    /**
     * Return the contact if possible
     *
     * @return ClientContact The ClientContact object
     */
    public function getContact()
    {
        if ($this->invitation) {
            return ClientContact::find($this->invitation->client_contact_id);
        } elseif (auth()->guard('contact')->user()) {
            return auth()->user();
        } else {
            return false;
        }
    }

    /************************************* Omnipay ******************************************
        authorize($options) - authorize an amount on the customer's card
        completeAuthorize($options) - handle return from off-site gateways after authorization
        capture($options) - capture an amount you have previously authorized
        purchase($options) - authorize and immediately capture an amount on the customer's card
        completePurchase($options) - handle return from off-site gateways after purchase
        refund($options) - refund an already processed transaction
        void($options) - generally can only be called up to 24 hours after submitting a transaction
        acceptNotification() - convert an incoming request from an off-site gateway to a generic notification object for further processing
     */

    protected function paymentDetails($input): array
    {
        $data = [
            'currency' => $this->client->getCurrencyCode(),
            'transactionType' => 'Purchase',
            'clientIp' => request()->getClientIp(),
        ];


        return $data;
    }

    public function purchase($data, $items)
    {
        $this->gateway();

        $response =        $this->gateway
            ->purchase($data)
            ->setItems($items)
            ->send();

        return $response;
        /*
        $this->purchaseResponse = (array)$response->getData();*/
    }

    public function completePurchase($data)
    {
        $this->gateway();

        return $this->gateway
            ->completePurchase($data)
            ->send();
    }

    public function createPayment($data, $status = Payment::STATUS_COMPLETED): Payment
    {
        $payment = PaymentFactory::create($this->client->company->id, $this->client->user->id);
        $payment->client_id = $this->client->id;
        $payment->company_gateway_id = $this->company_gateway->id;
        $payment->status_id = $status;
        $payment->currency_id = $this->client->getSetting('currency_id');
        $payment->date = Carbon::now();

        return $payment->service()->applyNumber()->save();
    }


    public function attachInvoices(Payment $payment, $hashed_ids): Payment
    {
        $transformed = $this->transformKeys($hashed_ids);
        $array = is_array($transformed) ? $transformed : [$transformed];

        $invoices = Invoice::whereIn('id', $array)
            ->whereClientId($this->client->id)
            ->get();

        $payment->invoices()->sync($invoices);
        $payment->save();

        return $payment;
    }

    /**
     * When a successful payment is made, we need to append the gateway fee
     * to an invoice
     *    
     * @param  PaymentResponseRequest $request The incoming payment request
     * @return void                            Success/Failure
     */
    public function appendGatewayFeeToInvoice(PaymentResponseRequest $request) :void
    {
        /*Payment meta data*/
        $payment_hash = $request->getPaymentHash();

        /*Payment invoices*/
        $payment_invoices = $payment_hash->invoices();
        
        /*Fee charged at gateway*/
        $fee_total = $payment_hash->fee_total;

        /*Sum of invoice amounts*/
        $invoice_totals = array_sum(array_column($payment_invoices,'amount'));
        
        /*Hydrate invoices*/
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($payment_invoices, 'invoice_id')))->get();

        /*Append gateway fee to invoice line item of first invoice*/
        if($fee_total != 0){
            $invoices->first()->service()->addGatewayFee($this->company_gateway, $invoice_totals)->save();

            //We need to update invoice balance / client balance at this point so
            //that payment record sanity is preserved //todo
        }

    }
}

