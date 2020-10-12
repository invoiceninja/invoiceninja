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

use App\Events\Invoice\InvoiceWasPaid;
use App\Factory\PaymentFactory;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\AbstractPaymentDriver;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SystemLogTrait;
use Illuminate\Support\Carbon;

/**
 * Class BaseDriver.
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
     * @param  const $payment_method    The GatewayType::constant
     * @return view                     Return a view for collecting payment method information
     */
    public function authorize($payment_method)
    {
    }

    /**
     * Executes purchase attempt for a given amount.
     *
     * @param  float   $amount                  The amount to be collected
     * @param  bool $return_client_response     Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed
     */
    public function purchase($amount, $return_client_response = false)
    {
    }

    /**
     * Executes a refund attempt for a given amount with a transaction_reference.
     *
     * @param  Payment $payment                The Payment Object
     * @param  float   $amount                 The amount to be refunded
     * @param  bool $return_client_response    Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
    }

    /**
     * Set the inbound request payment method type for access.
     *
     * @param int $payment_method_id The Payment Method ID
     */
    public function setPaymentMethod($payment_method_id)
    {
    }

    /**
     * Helper method to attach invoices to a payment.
     *
     * @param  Payment $payment    The payment
     * @param  array  $hashed_ids  The array of invoice hashed_ids
     * @return Payment             The payment object
     */
    public function attachInvoices(Payment $payment, PaymentHash $payment_hash): Payment
    {
        $paid_invoices = $payment_hash->invoices();
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($paid_invoices, 'invoice_id')))->get();
        $payment->invoices()->sync($invoices);

        $invoices->each(function ($invoice) use ($payment) {
            event(new InvoiceWasPaid($invoice, $payment->company, Ninja::eventVars()));
        });

        return $payment->service()->applyNumber()->save();
    }

    /**
     * Create a payment from an online payment.
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
     * Process an unattended payment.
     *
     * @param  ClientGatewayToken $cgt           The client gateway token object
     * @param  PaymentHash        $payment_hash  The Payment hash containing the payment meta data
     * @return Response                          The payment response
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }

    /**
     * When a successful payment is made, we need to append the gateway fee
     * to an invoice.
     *
     * @param  PaymentResponseRequest $request The incoming payment request
     * @return void                            Success/Failure
     */
    public function confirmGatewayFee(PaymentResponseRequest $request) :void
    {
        /*Payment meta data*/
        $payment_hash = $request->getPaymentHash();

        /*Payment invoices*/
        $payment_invoices = $payment_hash->invoices();

        // /*Fee charged at gateway*/
        $fee_total = $payment_hash->fee_total;

        // Sum of invoice amounts
        // $invoice_totals = array_sum(array_column($payment_invoices,'amount'));

        /*Hydrate invoices*/
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($payment_invoices, 'invoice_id')))->get();

        $invoices->each(function ($invoice) use ($fee_total) {
            if (collect($invoice->line_items)->contains('type_id', '3')) {
                $invoice->service()->toggleFeesPaid()->save();
                $invoice->client->service()->updateBalance($fee_total)->save();
                $invoice->ledger()->updateInvoiceBalance($fee_total, $notes = 'Gateway fee adjustment');
            }
        });
    }

    /**
     * In case of a payment failure we should always 
     * return the invoice to its original state
     * 
     * @param  PaymentHash $payment_hash The payment hash containing the list of invoices
     * @return void                    
     */
    public function unWindGatewayFees(PaymentHash $payment_hash)
    {

        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->get();

        $invoices->each(function ($invoice) {
            $invoice->service()->removeUnpaidGatewayFees();
        });
        
    }

    /**
     * Return the contact if possible.
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

}
