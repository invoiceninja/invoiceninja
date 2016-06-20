<?php namespace App\Services;

use Utils;
use Auth;
use URL;
use DateTime;
use Event;
use Cache;
use Omnipay;
use Session;
use CreditCard;
use WePay;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Account;
use App\Models\Country;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Activity;
use App\Models\AccountGateway;
use App\Http\Controllers\PaymentController;
use App\Models\AccountGatewayToken;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\AccountRepository;
use App\Services\BaseService;
use App\Events\PaymentWasCreated;
use App\Ninja\Datatables\PaymentDatatable;

class PaymentService extends BaseService
{
    public $lastError;
    protected $datatableService;

    public function __construct(PaymentRepository $paymentRepo, AccountRepository $accountRepo, DatatableService $datatableService)
    {
        $this->datatableService = $datatableService;
        $this->paymentRepo = $paymentRepo;
        $this->accountRepo = $accountRepo;
    }

    protected function getRepo()
    {
        return $this->paymentRepo;
    }

    public function createToken($paymentType, $gateway, $details, $accountGateway, $client, $contactId, &$customerReference = null, &$paymentMethod = null)
    {
        if ($accountGateway->gateway_id == GATEWAY_BRAINTREE) {
        } elseif ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            $wepay = Utils::setupWePay($accountGateway);
            try {
                if ($paymentType == PAYMENT_TYPE_WEPAY_ACH) {
                    // Persist bank details
                    $tokenResponse = $wepay->request('/payment_bank/persist', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'payment_bank_id' => intval($details['token']),
                    ));
                } else {
                    // Authorize credit card
                    $wepay->request('credit_card/authorize', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'credit_card_id' => intval($details['token']),
                    ));

                    // Update the callback uri and get the card details
                    $wepay->request('credit_card/modify', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'credit_card_id' => intval($details['token']),
                        'auto_update' => WEPAY_AUTO_UPDATE,
                        'callback_uri' => $accountGateway->getWebhookUrl(),
                    ));
                    $tokenResponse = $wepay->request('credit_card', array(
                        'client_id' => WEPAY_CLIENT_ID,
                        'client_secret' => WEPAY_CLIENT_SECRET,
                        'credit_card_id' => intval($details['token']),
                    ));
                }

                $customerReference = CUSTOMER_REFERENCE_LOCAL;
                $sourceReference = $details['token'];
            } catch (\WePayException $ex) {
                $this->lastError = $ex->getMessage();
                return;
            }
        } else {
            return null;
        }

        if ($customerReference) {
            $accountGatewayToken = AccountGatewayToken::where('client_id', '=', $client->id)
                ->where('account_gateway_id', '=', $accountGateway->id)->first();

            if (!$accountGatewayToken) {
                $accountGatewayToken = new AccountGatewayToken();
                $accountGatewayToken->account_id = $client->account->id;
                $accountGatewayToken->contact_id = $contactId;
                $accountGatewayToken->account_gateway_id = $accountGateway->id;
                $accountGatewayToken->client_id = $client->id;
            }

            $accountGatewayToken->token = $customerReference;
            $accountGatewayToken->save();

            $paymentMethod = $this->convertPaymentMethodFromGatewayResponse($tokenResponse, $accountGateway, $accountGatewayToken, $contactId);
            $paymentMethod->ip = \Request::ip();
            $paymentMethod->save();

        } else {
            $this->lastError = $tokenResponse->getMessage();
        }

        return $sourceReference;
    }

    public function convertPaymentMethodFromWePay($source, $accountGatewayToken = null, $paymentMethod = null) {
        // Creating a new one or updating an existing one
        if (!$paymentMethod) {
            $paymentMethod = $accountGatewayToken ? PaymentMethod::createNew($accountGatewayToken) : new PaymentMethod();
        }

        if ($source->payment_bank_id) {
            $paymentMethod->payment_type_id = PAYMENT_TYPE_ACH;
            $paymentMethod->last4 = $source->account_last_four;
            $paymentMethod->bank_name = $source->bank_name;
            $paymentMethod->source_reference = $source->payment_bank_id;

            switch($source->state) {
                case 'new':
                case 'pending':
                    $paymentMethod->status = 'new';
                    break;
                case 'authorized':
                    $paymentMethod->status = 'verified';
                    break;
            }
        } else {
            $paymentMethod->last4 = $source->last_four;
            $paymentMethod->payment_type_id = $this->parseCardType($source->credit_card_name);
            $paymentMethod->expiration = $source->expiration_year . '-' . $source->expiration_month . '-01';
            $paymentMethod->setRelation('payment_type', Cache::get('paymentTypes')->find($paymentMethod->payment_type_id));

            $paymentMethod->source_reference = $source->credit_card_id;
        }

        return $paymentMethod;
    }

    public function convertPaymentMethodFromGatewayResponse($gatewayResponse, $accountGateway, $accountGatewayToken = null, $contactId = null, $existingPaymentMethod = null) {
        if ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            if ($gatewayResponse instanceof \Omnipay\WePay\Message\CustomCheckoutResponse) {
                $wepay = \Utils::setupWePay($accountGateway);
                $paymentMethodType = $gatewayResponse->getData()['payment_method']['type'];

                $gatewayResponse = $wepay->request($paymentMethodType, array(
                    'client_id' => WEPAY_CLIENT_ID,
                    'client_secret' => WEPAY_CLIENT_SECRET,
                    $paymentMethodType.'_id' => $gatewayResponse->getData()['payment_method'][$paymentMethodType]['id'],
                ));

            }
            $paymentMethod = $this->convertPaymentMethodFromWePay($gatewayResponse, $accountGatewayToken, $existingPaymentMethod);
        }

        if (!empty($paymentMethod) && $accountGatewayToken && $contactId) {
            $paymentMethod->account_gateway_token_id = $accountGatewayToken->id;
            $paymentMethod->account_id = $accountGatewayToken->account_id;
            $paymentMethod->contact_id = $contactId;
            $paymentMethod->save();

            if (!$paymentMethod->account_gateway_token->default_payment_method_id) {
                $paymentMethod->account_gateway_token->default_payment_method_id = $paymentMethod->id;
                $paymentMethod->account_gateway_token->save();
            }
        }

        return $paymentMethod;
    }


    public function autoBillInvoice($invoice)
    {
        $client = $invoice->client;
        $account = $client->account;
        $invitation = $invoice->invitations->first();

        if ( ! $invitation) {
            return false;
        }

        $paymentDriver = $account->paymentDriver($invitation, GATEWAY_TYPE_TOKEN);
        $customer = $paymentDriver->customer();

        if ( ! $customer) {
            return false;
        }

        $paymentMethod = $customer->default_payment_method;

        if ($paymentMethod->requiresDelayedAutoBill()) {
            $invoiceDate = \DateTime::createFromFormat('Y-m-d', $invoice->invoice_date);
            $minDueDate = clone $invoiceDate;
            $minDueDate->modify('+10 days');

            if (date_create() < $minDueDate) {
                // Can't auto bill now
                return false;
            }

            if ($invoice->partial > 0) {
                // The amount would be different than the amount in the email
                return false;
            }

            $firstUpdate = Activity::where('invoice_id', '=', $invoice->id)
                ->where('activity_type_id', '=', ACTIVITY_TYPE_UPDATE_INVOICE)
                ->first();

            if ($firstUpdate) {
                $backup = json_decode($firstUpdate->json_backup);

                if ($backup->balance != $invoice->balance || $backup->due_date != $invoice->due_date) {
                    // It's changed since we sent the email can't bill now
                    return false;
                }
            }

            if ($invoice->payments->count()) {
                // ACH requirements are strict; don't auto bill this
                return false;
            }
        }


        return $paymentDriver->completeOnsitePurchase(false, $paymentMethod);

        /*
        if ($accountGateway->gateway_id == GATEWAY_WEPAY) {
            $details['transaction_id'] = 'autobill_'.$invoice->id;
        }
        */
    }

    public function getDatatable($clientPublicId, $search)
    {
        $datatable = new PaymentDatatable( ! $clientPublicId, $clientPublicId);
        $query = $this->paymentRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->datatableService->createDatatable($datatable, $query);
    }


    public function bulk($ids, $action, $params = array())
    {
        if ($action == 'refund') {
            if ( ! $ids ) {
                return 0;
            }

            $payments = $this->getRepo()->findByPublicIdsWithTrashed($ids);
            $successful = 0;

            foreach ($payments as $payment) {
                if (Auth::user()->can('edit', $payment)) {
                    $amount = !empty($params['amount']) ? floatval($params['amount']) : null;
                    $accountGateway = $payment->account_gateway;
                    $paymentDriver = $accountGateway->paymentDriver();
                    if ($paymentDriver->refundPayment($payment, $amount)) {
                        $successful++;
                    }
                }
            }

            return $successful;
        } else {
            return parent::bulk($ids, $action);
        }
    }

}
