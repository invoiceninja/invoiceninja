<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Exceptions\PaymentFailed;
use App\Factory\PaymentFactory;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Mail\PaymentFailedMailer;
use App\Jobs\Ninja\TransactionLog;
use App\Jobs\Util\SystemLogger;
use App\Mail\Admin\ClientPaymentFailureObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Models\TransactionEvent;
use App\Services\Subscription\SubscriptionService;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SystemLogTrait;
use Checkout\Library\Exceptions\CheckoutHttpException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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

    /* The initialized gateway driver class*/
    public $payment_method;

    /* PaymentHash */
    public $payment_hash;

    /* Array of payment methods */
    public static $methods = [];

    /** @var array */
    public $required_fields = [];

    public function __construct(CompanyGateway $company_gateway, Client $client = null, $invitation = false)
    {
        $this->company_gateway = $company_gateway;
        $this->invitation = $invitation;
        $this->client = $client;
    }

    /**
     * Required fields for client to fill, to proceed with gateway actions.
     *
     * @return array[]
     */
    public function getClientRequiredFields(): array
    {
        return [];
    }

    /**
     * Authorize a payment method.
     *
     * Returns a reusable token for storage for future payments
     *
     * @param array $data
     * @return mixed Return a view for collecting payment method information
     */
    public function authorizeView(array $data)
    {
    }

    /**
     * The payment authorization response
     *
     * @param  Request $request
     * @return mixed Return a response for collecting payment method information
     */
    public function authorizeResponse(Request $request)
    {
    }

    /**
     * Process a payment
     *
     * @param  array $data
     * @return mixed Return a view for the payment
     */
    public function processPaymentView(array $data)
    {
    }

    /**
     * Process payment response
     *
     * @param  Request $request
     * @return mixed   Return a response for the payment
     */
    public function processPaymentResponse(Request $request)
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
     * Process an unattended payment.
     *
     * @param ClientGatewayToken $cgt The client gateway token object
     * @param PaymentHash $payment_hash The Payment hash containing the payment meta data
     * @return void The payment response
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }

    /**
     * Detaches a payment method from the gateway
     *
     * @param  ClientGatewayToken $token The gateway token
     * @return bool                      boolean response
     */
    public function detach(ClientGatewayToken $token)
    {
        return true;
    }

    /**
     * Set the inbound request payment method type for access.
     *
     * @param int $payment_method_id The Payment Method ID
     */
    public function setPaymentMethod($payment_method_id)
    {
    }

    public function setClient(Client $client)
    {
        $this->client = $client;
    }
    /************************** Helper methods *************************************/

    public function setPaymentHash(PaymentHash $payment_hash)
    {
        $this->payment_hash = $payment_hash;

        return $this;
    }

    /**
     * Helper method to attach invoices to a payment.
     *
     * @param Payment $payment The payment
     * @param PaymentHash $payment_hash
     * @return Payment             The payment object
     */
    public function attachInvoices(Payment $payment, PaymentHash $payment_hash): Payment
    {
        $paid_invoices = $payment_hash->invoices();
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($paid_invoices, 'invoice_id')))->withTrashed()->get();
        $payment->invoices()->sync($invoices);

        $payment->service()->applyNumber()->save();

        $invoices->each(function ($invoice) use ($payment) {
            event(new InvoiceWasPaid($invoice, $payment, $payment->company, Ninja::eventVars()));
        });

        return $payment;
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
        if (in_array($status, [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING])) {
            $this->confirmGatewayFee();
        }

        /*Never create a payment with a duplicate transaction reference*/
        if (array_key_exists('transaction_reference', $data)) {
            $_payment = Payment::where('transaction_reference', $data['transaction_reference'])
                               ->where('client_id', $this->client->id)
                               ->first();

            if ($_payment) {
                return $_payment;
            }
        }

        $payment = PaymentFactory::create($this->client->company->id, $this->client->user->id);
        $payment->client_id = $this->client->id;
        $payment->company_gateway_id = $this->company_gateway->id;
        $payment->status_id = $status;
        $payment->currency_id = $this->client->getSetting('currency_id');
        $payment->date = Carbon::now();
        $payment->gateway_type_id = $data['gateway_type_id'];

        $client_contact = $this->getContact();
        $client_contact_id = $client_contact ? $client_contact->id : null;

        $payment->amount = $data['amount'];
        $payment->type_id = $data['payment_type'];
        $payment->transaction_reference = $data['transaction_reference'];
        $payment->client_contact_id = $client_contact_id;
        $payment->saveQuietly();

        /* Return early if the payment is not completed or pending*/
        if (! in_array($status, [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING])) {
            return $payment;
        }

        $this->payment_hash->payment_id = $payment->id;
        $this->payment_hash->save();

        $this->attachInvoices($payment, $this->payment_hash);

        if ($this->payment_hash->credits_total() > 0) {
            $payment = $payment->service()->applyCredits($this->payment_hash)->save();
        }

        $payment->service()->updateInvoicePayment($this->payment_hash);

        event('eloquent.created: App\Models\Payment', $payment);

        if ($this->client->getSetting('client_online_payment_notification') && in_array($status, [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING])) {
            $payment->service()->sendEmail();
        }

        //todo
        //catch any payment failures here also and fire a subsequent failure email if necessary? note only need for delayed payment forms
        //perhaps this type of functionality should be handled higher up to provide better context?

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        if (property_exists($this->payment_hash->data, 'billing_context') && $status == Payment::STATUS_COMPLETED) {
            $billing_subscription = \App\Models\Subscription::find($this->payment_hash->data->billing_context->subscription_id);

            // To access campaign hash => $this->payment_hash->data->billing_context->campaign;
            // To access campaign data => Cache::get(CAMPAIGN_HASH)
            // To access utm data => session()->get('utm-' . CAMPAIGN_HASH);

            (new SubscriptionService($billing_subscription))->completePurchase($this->payment_hash);
        }

        return $payment->service()->applyNumber()->save();
    }

    /**
     * When a successful payment is made, we need to append the gateway fee
     * to an invoice.
     *
     * @param  PaymentResponseRequest $request The incoming payment request
     * @return void                            Success/Failure
     */
    public function confirmGatewayFee() :void
    {

        /*Payment invoices*/
        $payment_invoices = $this->payment_hash->invoices();

        /*Fee charged at gateway*/
        $fee_total = $this->payment_hash->fee_total;

        /*Hydrate invoices*/
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($payment_invoices, 'invoice_id')))->withTrashed()->get();

        $invoices->each(function ($invoice) use ($fee_total) {
            if (collect($invoice->line_items)->contains('type_id', '3')) {
                $invoice->service()->toggleFeesPaid()->save();
            }

            $transaction = [
                'invoice' => $invoice->transaction_event(),
                'payment' => [],
                'client' => $invoice->client->transaction_event(),
                'credit' => [],
                'metadata' => [],
            ];

            TransactionLog::dispatch(TransactionEvent::INVOICE_FEE_APPLIED, $transaction, $invoice->company->db);
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
        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->get();

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

    /**
     * Store payment method as company gateway token.
     *
     * @param array $data
     * @return null|ClientGatewayToken
     */
    public function storeGatewayToken(array $data, array $additional = []): ?ClientGatewayToken
    {
        $company_gateway_token = new ClientGatewayToken();
        $company_gateway_token->company_id = $this->client->company->id;
        $company_gateway_token->client_id = $this->client->id;
        $company_gateway_token->token = $data['token'];
        $company_gateway_token->company_gateway_id = $this->company_gateway->id;
        $company_gateway_token->gateway_type_id = $data['payment_method_id'];
        $company_gateway_token->meta = $data['payment_meta'];

        foreach ($additional as $key => $value) {
            $company_gateway_token->{$key} = $value;
        }

        $company_gateway_token->save();

        if ($this->client->gateway_tokens->count() == 1) {
            $this->client->gateway_tokens()->update(['is_default' => 0]);

            $company_gateway_token->is_default = 1;
            $company_gateway_token->save();
        }

        return $company_gateway_token;
    }

    public function processInternallyFailedPayment($gateway, $e)
    {
        if (! is_null($this->payment_hash)) {
            $this->unWindGatewayFees($this->payment_hash);
        }

        $error = $e->getMessage();

        if (! $this->payment_hash) {
            throw new PaymentFailed($error, $e->getCode());
        }

        $amount = array_sum(array_column($this->payment_hash->invoices(), 'amount')) + $this->payment_hash->fee_total;

        $this->sendFailureMail($error);

        SystemLogger::dispatch(
            $gateway->payment_hash,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_ERROR,
            $gateway::SYSTEM_LOG_TYPE,
            $gateway->client,
            $gateway->client->company,
        );

        throw new PaymentFailed($error, $e->getCode());
    }

    public function sendFailureMail($error)
    {
        if(is_object($error)){
            $error = 'Payment Aborted';
        }

        if (! is_null($this->payment_hash)) {
            $this->unWindGatewayFees($this->payment_hash);
        }

        if (! $error) {
            $error = '';
        }

        PaymentFailedMailer::dispatch(
            $this->payment_hash,
            $this->client->company,
            $this->client,
            $error
        );
    }

    public function clientPaymentFailureMailer($error)
    {
        if ($this->payment_hash && is_array($this->payment_hash->invoices())) {
            $nmo = new NinjaMailerObject;
            $nmo->mailable = new NinjaMailer((new ClientPaymentFailureObject($this->client, $error, $this->client->company, $this->payment_hash))->build());
            $nmo->company = $this->client->company;
            $nmo->settings = $this->client->company->settings;

            $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($this->payment_hash->invoices(), 'invoice_id')))->withTrashed()->get();

            $invoices->each(function ($invoice) {
                $invoice->service()->touchPdf();
            });

            $invoices->first()->invitations->each(function ($invitation) use ($nmo) {
                if ((bool) $invitation->contact->send_email !== false && $invitation->contact->email) {
                    $nmo->to_user = $invitation->contact;
                    NinjaMailerJob::dispatch($nmo);
                }
            });
        }
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

    /*Generic Global unsuccessful transaction method when the client is present*/
    public function processUnsuccessfulTransaction($response, $client_present = true)
    {
        $error = array_key_exists('error', $response) ? $response['error'] : 'Undefined Error';
        $error_code = array_key_exists('error_code', $response) ? $response['error_code'] : 'Undefined Error Code';

        $this->unWindGatewayFees($this->payment_hash);

        $this->sendFailureMail($error);

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new ClientPaymentFailureObject($this->client, $error, $this->client->company, $this->payment_hash))->build());
        $nmo->company = $this->client->company;
        $nmo->settings = $this->client->company->settings;

        $invoices = Invoice::whereIn('id', $this->transformKeys(array_column($this->payment_hash->invoices(), 'invoice_id')))->withTrashed()->get();

        $invoices->each(function ($invoice) {
            $invoice->service()->touchPdf();
        });

        $invoices->first()->invitations->each(function ($invitation) use ($nmo) {
            if (! $invitation->contact->trashed()) {
                $nmo->to_user = $invitation->contact;
                NinjaMailerJob::dispatch($nmo);
            }
        });

        $message = [
            'server_response' => $response,
            'data' => $this->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYTRACE,
            $this->client,
            $this->client->company,
        );

        if ($client_present) {
            throw new PaymentFailed($error, 500);
        }
    }

    public function checkRequirements()
    {
        if ($this->company_gateway->require_billing_address) {
            if ($this->checkRequiredResource($this->client->address1)) {
                $this->required_fields[] = 'billing_address1';
            }

            if ($this->checkRequiredResource($this->client->address2)) {
                $this->required_fields[] = 'billing_address2';
            }

            if ($this->checkRequiredResource($this->client->city)) {
                $this->required_fields[] = 'billing_city';
            }

            if ($this->checkRequiredResource($this->client->state)) {
                $this->required_fields[] = 'billing_state';
            }

            if ($this->checkRequiredResource($this->client->postal_code)) {
                $this->required_fields[] = 'billing_postal_code';
            }

            if ($this->checkRequiredResource($this->client->country_id)) {
                $this->required_fields[] = 'billing_country';
            }
        }

        if ($this->company_gateway->require_shipping_address) {
            if ($this->checkRequiredResource($this->client->shipping_address1)) {
                $this->required_fields[] = 'shipping_address1';
            }

            if ($this->checkRequiredResource($this->client->shipping_address2)) {
                $this->required_fields[] = 'shipping_address2';
            }

            if ($this->checkRequiredResource($this->client->shipping_city)) {
                $this->required_fields[] = 'shipping_city';
            }

            if ($this->checkRequiredResource($this->client->shipping_state)) {
                $this->required_fields[] = 'shipping_state';
            }

            if ($this->checkRequiredResource($this->client->shipping_postal_code)) {
                $this->required_fields[] = 'shipping_postal_code';
            }

            if ($this->checkRequiredResource($this->client->shipping_country_id)) {
                $this->required_fields[] = 'shipping_country';
            }
        }

        if ($this->company_gateway->require_client_name) {
            if ($this->checkRequiredResource($this->client->name)) {
                $this->required_fields[] = 'name';
            }
        }

        if ($this->company_gateway->require_client_phone) {
            if ($this->checkRequiredResource($this->client->phone)) {
                $this->required_fields[] = 'phone';
            }
        }

        if ($this->company_gateway->require_contact_email) {
            if ($this->checkRequiredResource($this->email)) {
                $this->required_fields[] = 'contact_email';
            }
        }

        // if ($this->company_gateway->require_contact_name) {
        //     if ($this->checkRequiredResource($this->first_name)) {
        //         $this->required_fields[] = 'contact_first_name';
        //     }

        //     if ($this->checkRequiredResource($this->last_name)) {
        //         $this->required_fields[] = 'contact_last_name';
        //     }
        // }

        if ($this->company_gateway->require_postal_code) {
            // In case "require_postal_code" is true, we don't need billing address.

            foreach ($this->required_fields as $position => $field) {
                if (Str::startsWith($field, 'billing')) {
                    unset($this->required_fields[$position]);
                }
            }

            if ($this->checkRequiredResource($this->client->postal_code)) {
                $this->required_fields[] = 'postal_code';
            }
        }

        return $this;
    }

    public function getCompanyGatewayId(): int
    {
        return $this->company_gateway->id;
    }

    public function logSuccessfulGatewayResponse($response, $gateway_const)
    {
        SystemLogger::dispatch(
            $response,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            $gateway_const,
            $this->client,
            $this->client->company,
        );
    }

    public function genericWebhookUrl()
    {
        return route('payment_notification_webhook', [
            'company_key' => $this->client->company->company_key,
            'company_gateway_id' => $this->encodePrimaryKey($this->company_gateway->id),
            'client' => $this->encodePrimaryKey($this->client->id),
        ]);
    }

    /* Performs an extra iterate on the gatewayTypes() array and passes back only the enabled gateways*/
    public function gatewayTypeEnabled($type)
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }

    /**
     * Generic description handler
     */
    public function getDescription(bool $abbreviated = true)
    {
        if (! $this->payment_hash) {
            return '';
        }

        if ($abbreviated) {
            return \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray());
        }

        return sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray()));
    }

    public function disconnect()
    {
        return true;
    }
}
