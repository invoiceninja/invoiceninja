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

use App\Events\Invoice\InvoiceWasPaid;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\PaymentDrivers\AbstractPaymentDriver;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SystemLogTrait;
use Illuminate\Support\Carbon;

/**
 * Class BaseDriver
 * @package App\PaymentDrivers
 *
 */
class BaseDriver extends AbstractPaymentDriver
{
    use SystemLogTrait;
    use MakesHash;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    public $invitation;

    /* Gateway capabilities */
    public $refundable = false;

    /* Token billing */
    public $token_billing = false;

    /* Authorise payment methods */
    public $can_authorise_credit_card = false;

    /* The client */
    public $client;

    /* The initiated gateway driver class*/
    public $payment_method;

    public static $methods = [];

    public function __construct(CompanyGateway $company_gateway, Client $client = null, $invitation = false)
    {
        $this->company_gateway = $company_gateway;

        $this->invitation = $invitation;

        $this->client = $client;
    }

    /**
     * Authorize a payment method.
     *
     * Returns a reusable token for storage for future payments
     * @param  const $payment_method the GatewayType::constant
     * @return view                 Return a view for collecting payment method information
     */
    public function authorize($payment_method) {}
    
    /**
     * Executes purchase attempt for a given amount
     * 
     * @param  float   $amount                 The amount to be collected
     * @param  boolean $return_client_response Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed                          
     */
    public function purchase($amount, $return_client_response = false) {}

    /**
     * Executes a refund attempt for a given amount with a transaction_reference
     * 
     * @param  Payment $payment                The Payment Object
     * @param  float   $amount                 The amount to be refunded
     * @param  boolean $return_client_response Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed                          
     */
    public function refund(Payment $payment, $amount, $return_client_response = false) {}

    /**
     * Set the inbound request payment method type for access.
     * 
     * @param int $payment_method_id The Payment Method ID
     */
    public function setPaymentMethod($payment_method_id){}

    /**
     * Helper method to attach invoices to a payment
     * 
     * @param  Payment $payment    The payment
     * @param  array  $hashed_ids  The array of invoice hashed_ids
     * @return Payment             The payment object
     */
    public function attachInvoices(Payment $payment, $hashed_ids): Payment
    {
        $transformed = $this->transformKeys($hashed_ids);
        $array = is_array($transformed) ? $transformed : [$transformed];

        $invoices = Invoice::whereIn('id', $array)
            ->whereClientId($this->client->id)
            ->get();

        $payment->invoices()->sync($invoices);
        $payment->save();

        $payment->service()->applyNumber()->save();

        $invoices->each(function ($invoice) use($payment){
            event(new InvoiceWasPaid($invoice, $payment->company, Ninja::eventVars()));
        });

        return $payment;
    }

    /**
     * Create a payment from an online payment
     * 
     * @param  array $data     Payment data
     * @param  int   $status   The payment status_id
     * @return Payment         The payment object
     */
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

    /**
     * Process an unattended payment
     * 
     * @param  ClientGatewayToken $cgt      The client gateway token object
     * @param  float              $amount   The amount to bill
     * @param  Invoice            $invoice  Optional Invoice object being paid
     * @return Response                     The payment response
     */
    public function tokenBilling(ClientGatewayToken $cgt, float $amount, ?Invoice $invoice = null) {}
}
