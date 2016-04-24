<?php namespace App\Services;

use Utils;
use Auth;
use URL;
use DateTime;
use Event;
use Omnipay;
use Session;
use CreditCard;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Country;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\AccountGatewayToken;
use App\Ninja\Repositories\PaymentRepository;
use App\Ninja\Repositories\AccountRepository;
use App\Services\BaseService;
use App\Events\PaymentWasCreated;

class PaymentService extends BaseService
{
    public $lastError;
    protected $datatableService;
    
    protected static $refundableGateways = array(
        GATEWAY_STRIPE
    );

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

    public function createGateway($accountGateway)
    {
        $gateway = Omnipay::create($accountGateway->gateway->provider);
        $gateway->initialize((array) $accountGateway->getConfig());

        if ($accountGateway->isGateway(GATEWAY_DWOLLA)) {
            if ($gateway->getSandbox() && isset($_ENV['DWOLLA_SANDBOX_KEY']) && isset($_ENV['DWOLLA_SANSBOX_SECRET'])) {
                $gateway->setKey($_ENV['DWOLLA_SANDBOX_KEY']);
                $gateway->setSecret($_ENV['DWOLLA_SANSBOX_SECRET']);
            } elseif (isset($_ENV['DWOLLA_KEY']) && isset($_ENV['DWOLLA_SECRET'])) {
                $gateway->setKey($_ENV['DWOLLA_KEY']);
                $gateway->setSecret($_ENV['DWOLLA_SECRET']);
            }
        }

        return $gateway;
    }

    public function getPaymentDetails($invitation, $accountGateway, $input = null)
    {
        $invoice = $invitation->invoice;
        $account = $invoice->account;
        $key = $invoice->account_id.'-'.$invoice->invoice_number;
        $currencyCode = $invoice->client->currency ? $invoice->client->currency->code : ($invoice->account->currency ? $invoice->account->currency->code : 'USD');

        if ($input) {
            $data = self::convertInputForOmnipay($input);
            $data['email'] = $invitation->contact->email;
            Session::put($key, $data);
        } elseif (Session::get($key)) {
            $data = Session::get($key);
        } else {
            $data = $this->createDataForClient($invitation);
        }

        $card = !empty($data['number']) ? new CreditCard($data) : null;
        $data = [
            'amount' => $invoice->getRequestedAmount(),
            'card' => $card,
            'currency' => $currencyCode,
            'returnUrl' => URL::to('complete'),
            'cancelUrl' => $invitation->getLink(),
            'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}",
            'transactionId' => $invoice->invoice_number,
            'transactionType' => 'Purchase',
        ];

        if ($accountGateway->isGateway(GATEWAY_PAYPAL_EXPRESS) || $accountGateway->isGateway(GATEWAY_PAYPAL_PRO)) {
            $data['ButtonSource'] = 'InvoiceNinja_SP';
        };

        return $data;
    }

    public function convertInputForOmnipay($input)
    {
        $data = [
            'firstName' => $input['first_name'],
            'lastName' => $input['last_name'],
            'number' => isset($input['card_number']) ? $input['card_number'] : null,
            'expiryMonth' => isset($input['expiration_month']) ? $input['expiration_month'] : null,
            'expiryYear' => isset($input['expiration_year']) ? $input['expiration_year'] : null,
            'cvv' => isset($input['cvv']) ? $input['cvv'] : '',
        ];

        if (isset($input['country_id'])) {
            $country = Country::find($input['country_id']);

            $data = array_merge($data, [
                'billingAddress1' => $input['address1'],
                'billingAddress2' => $input['address2'],
                'billingCity' => $input['city'],
                'billingState' => $input['state'],
                'billingPostcode' => $input['postal_code'],
                'billingCountry' => $country->iso_3166_2,
                'shippingAddress1' => $input['address1'],
                'shippingAddress2' => $input['address2'],
                'shippingCity' => $input['city'],
                'shippingState' => $input['state'],
                'shippingPostcode' => $input['postal_code'],
                'shippingCountry' => $country->iso_3166_2
            ]);
        }

        return $data;
    }

    public function createDataForClient($invitation)
    {
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $contact = $invitation->contact ?: $client->contacts()->first();

        return [
            'email' => $contact->email,
            'company' => $client->getDisplayName(),
            'firstName' => $contact->first_name,
            'lastName' => $contact->last_name,
            'billingAddress1' => $client->address1,
            'billingAddress2' => $client->address2,
            'billingCity' => $client->city,
            'billingPostcode' => $client->postal_code,
            'billingState' => $client->state,
            'billingCountry' => $client->country ? $client->country->iso_3166_2 : '',
            'billingPhone' => $contact->phone,
            'shippingAddress1' => $client->address1,
            'shippingAddress2' => $client->address2,
            'shippingCity' => $client->city,
            'shippingPostcode' => $client->postal_code,
            'shippingState' => $client->state,
            'shippingCountry' => $client->country ? $client->country->iso_3166_2 : '',
            'shippingPhone' => $contact->phone,
        ];
    }

    public function createToken($gateway, $details, $accountGateway, $client, $contactId, &$customerReference = null)
    {
        $tokenResponse = $gateway->createCard($details)->send();
        $cardReference = $tokenResponse->getCardReference();
        $customerReference = $tokenResponse->getCustomerReference();
        
        if ($customerReference == $cardReference) {
            // This customer was just created; find the card
            $data = $tokenResponse->getData();
            if(!empty($data['default_source'])) {
                $cardReference = $data['default_source'];
            }
        }

        if ($customerReference) {
            $token = AccountGatewayToken::where('client_id', '=', $client->id)
            ->where('account_gateway_id', '=', $accountGateway->id)->first();

            if (!$token) {
                $token = new AccountGatewayToken();
                $token->account_id = $client->account->id;
                $token->contact_id = $contactId;
                $token->account_gateway_id = $accountGateway->id;
                $token->client_id = $client->id;
            }

            $token->token = $customerReference;
            $token->save();
        } else {
            $this->lastError = $tokenResponse->getMessage();
        }

        return $cardReference;
    }

    public function getCheckoutComToken($invitation)
    {
        $token = false;
        $invoice = $invitation->invoice;
        $client = $invoice->client;
        $account = $invoice->account;

        $accountGateway = $account->getGatewayConfig(GATEWAY_CHECKOUT_COM);
        $gateway = $this->createGateway($accountGateway);

        $response = $gateway->purchase([
            'amount' => $invoice->getRequestedAmount(),
            'currency' => $client->currency ? $client->currency->code : ($account->currency ? $account->currency->code : 'USD')
        ])->send();

        if ($response->isRedirect()) {
            $token = $response->getTransactionReference();
        }

        Session::set($invitation->id . 'payment_type', PAYMENT_TYPE_CREDIT_CARD);

        return $token;
    }

    public function createPayment($invitation, $accountGateway, $ref, $payerId = null, $last4 = null, $expiration = null, $card_type_id = null, $routing_number = null)
    {
        $invoice = $invitation->invoice;

        $payment = Payment::createNew($invitation);
        $payment->invitation_id = $invitation->id;
        $payment->account_gateway_id = $accountGateway->id;
        $payment->invoice_id = $invoice->id;
        $payment->amount = $invoice->getRequestedAmount();
        $payment->client_id = $invoice->client_id;
        $payment->contact_id = $invitation->contact_id;
        $payment->transaction_reference = $ref;
        $payment->payment_date = date_create()->format('Y-m-d');
        $payment->last4 = $last4;
        $payment->expiration = $expiration;
        $payment->card_type_id = $card_type_id;
        $payment->routing_number = $routing_number;
        
        if ($payerId) {
            $payment->payer_id = $payerId;
        }

        $payment->save();

        // enable pro plan for hosted users
        if ($invoice->account->account_key == NINJA_ACCOUNT_KEY) {
            foreach ($invoice->invoice_items as $invoice_item) {
                // Hacky, but invoices don't have meta fields to allow us to store this easily
                if (1 == preg_match('/^Plan - (.+) \((.+)\)$/', $invoice_item->product_key, $matches)) {
                    $plan = strtolower($matches[1]);
                    $term = strtolower($matches[2]);
                } elseif ($invoice_item->product_key == 'Pending Monthly') {
                    $pending_monthly = true;
                }
            }
            
            if (!empty($plan)) { 
                $account = Account::with('users')->find($invoice->client->public_id);
                
                if(
                    $account->company->plan != $plan
                    || DateTime::createFromFormat('Y-m-d', $account->company->plan_expires) >= date_create('-7 days')
                ) {
                    // Either this is a different plan, or the subscription expired more than a week ago
                    // Reset any grandfathering
                    $account->company->plan_started = date_create()->format('Y-m-d');
                }
                            
                if (
                    $account->company->plan == $plan
                    && $account->company->plan_term == $term 
                    && DateTime::createFromFormat('Y-m-d', $account->company->plan_expires) >= date_create()
                ) {
                    // This is a renewal; mark it paid as of when this term expires
                    $account->company->plan_paid = $account->company->plan_expires;
                } else {
                    $account->company->plan_paid = date_create()->format('Y-m-d');
                }
                
                $account->company->payment_id = $payment->id;
                $account->company->plan = $plan;
                $account->company->plan_term = $term;
                $account->company->plan_expires = DateTime::createFromFormat('Y-m-d', $account->company->plan_paid)
                    ->modify($term == PLAN_TERM_MONTHLY ? '+1 month' : '+1 year')->format('Y-m-d');
                                
                if (!empty($pending_monthly)) {
                    $account->company->pending_plan = $plan;
                    $account->company->pending_term = PLAN_TERM_MONTHLY;
                } else {
                    $account->company->pending_plan = null;
                    $account->company->pending_term = null;
                }
                
                $account->company->save();
            }
        }

        return $payment;
    }

    public function completePurchase($gateway, $accountGateway, $details, $token)
    {
        if ($accountGateway->isGateway(GATEWAY_MOLLIE)) {
            $details['transactionReference'] = $token;
            $response = $gateway->fetchTransaction($details)->send();
            return $gateway->fetchTransaction($details)->send();
        } else {

            return $gateway->completePurchase($details)->send();
        }
    }

    public function autoBillInvoice($invoice)
    {
        $client = $invoice->client;
        $account = $invoice->account;
        $invitation = $invoice->invitations->first();
        $accountGateway = $account->getGatewayConfig(GATEWAY_STRIPE);
        $token = $client->getGatewayToken();

        if (!$invitation || !$accountGateway || !$token) {
            return false;
        }

        // setup the gateway/payment info
        $gateway = $this->createGateway($accountGateway);
        $details = $this->getPaymentDetails($invitation, $accountGateway);
        $details['customerReference'] = $token;

        // submit purchase/get response
        $response = $gateway->purchase($details)->send();

        if ($response->isSuccessful()) {
            $ref = $response->getTransactionReference();
            return $this->createPayment($invitation, $accountGateway, $ref);
        } else {
            return false;
        }
    }

    public function getDatatable($clientPublicId, $search)
    {
        $query = $this->paymentRepo->find($clientPublicId, $search);

        if(!Utils::hasPermission('view_all')){
            $query->where('payments.user_id', '=', Auth::user()->id);
        }

        return $this->createDatatable(ENTITY_PAYMENT, $query, !$clientPublicId, false, 
                ['invoice_number', 'transaction_reference', 'payment_type', 'amount', 'payment_date']);
    }

    protected function getDatatableColumns($entityType, $hideClient)
    {
        return [
            [
                'invoice_number',
                function ($model) {
                    if(!Invoice::canEditItemByOwner($model->invoice_user_id)){
                        return $model->invoice_number;
                    }
                    
                    return link_to("invoices/{$model->invoice_public_id}/edit", $model->invoice_number, ['class' => Utils::getEntityRowClass($model)])->toHtml();
                }
            ],
            [
                'client_name',
                function ($model) {
                    if(!Client::canViewItemByOwner($model->client_user_id)){
                        return Utils::getClientDisplayName($model);
                    }
                    
                    return $model->client_public_id ? link_to("clients/{$model->client_public_id}", Utils::getClientDisplayName($model))->toHtml() : '';
                },
                ! $hideClient
            ],
            [
                'transaction_reference',
                function ($model) {
                    return $model->transaction_reference ? $model->transaction_reference : '<i>Manual entry</i>';
                }
            ],
            [
                'payment_type',
                function ($model) {
                    return $model->payment_type ? $model->payment_type : ($model->account_gateway_id ? $model->gateway_name : '');
                }
            ],
            [
                'source',
                function ($model) { 
                    if (!$model->card_type_code) return '';
                    $card_type = trans("texts.card_" . $model->card_type_code);
                    $expiration = trans('texts.card_expiration', array('expires'=>Utils::fromSqlDate($model->expiration, false)->format('m/d')));
                    return '<img height="22" src="'.URL::to('/images/credit_cards/'.$model->card_type_code.'.png').'" alt="'.htmlentities($card_type).'">&nbsp; &bull;&bull;&bull;'.$model->last4.' '.$expiration;
                }
            ],
            [
                'amount',
                function ($model) {
                    return Utils::formatMoney($model->amount, $model->currency_id, $model->country_id);
                }
            ],
            [
                'payment_date',
                function ($model) {
                    return Utils::dateToString($model->payment_date);
                }
            ],
            [
                'payment_status_name',
                function ($model) use ($entityType) {
                    return self::getStatusLabel($entityType, $model);
                }
            ]
        ];
    }

    protected function getDatatableActions($entityType)
    {
        return [
            [
                trans('texts.edit_payment'),
                function ($model) {
                    return URL::to("payments/{$model->public_id}/edit");
                },
                function ($model) {
                    return Payment::canEditItem($model);
                }
            ],
            [
                trans('texts.refund_payment'),
                function ($model) {
                    $max_refund = number_format($model->amount - $model->refunded, 2);
                    $formatted = Utils::formatMoney($max_refund, $model->currency_id, $model->country_id);
                    $symbol = Utils::getFromCache($model->currency_id, 'currencies')->symbol;
                    return "javascript:showRefundModal({$model->public_id}, '{$max_refund}', '{$formatted}', '{$symbol}')";
                },
                function ($model) {
                    return Payment::canEditItem($model) && (
                        ($model->transaction_reference && in_array($model->gateway_id , static::$refundableGateways))
                        || $model->payment_type_id == PAYMENT_TYPE_CREDIT
                    );
                }
            ]
        ];
    }
    
    public function bulk($ids, $action, $params = array())
    {
        if ($action == 'refund') {
            if ( ! $ids ) {
                return 0;
            }

            $payments = $this->getRepo()->findByPublicIdsWithTrashed($ids);

            foreach ($payments as $payment) {
                if($payment->canEdit()){
                    if(!empty($params['amount'])) {
                        $this->refund($payment, floatval($params['amount']));
                    } else {
                        $this->refund($payment);
                    }  
                }
            }

            return count($payments);
        } else {
            return parent::bulk($ids, $action);
        }
    }
    
    private function getStatusLabel($entityType, $model)
    {
        $label = trans("texts.status_" . strtolower($model->payment_status_name));
        $class = 'default';
        switch ($model->payment_status_id) {
            case PAYMENT_STATUS_PENDING:
                $class = 'info';
                break;
            case PAYMENT_STATUS_COMPLETED:
                $class = 'success';
                break;
            case PAYMENT_STATUS_FAILED:
                $class = 'danger';
                break;
            case PAYMENT_STATUS_PARTIALLY_REFUNDED:
                $label = trans('texts.status_partially_refunded_amount', [
                    'amount' => Utils::formatMoney($model->refunded, $model->currency_id, $model->country_id),
                ]);
                $class = 'primary';
                break;
            case PAYMENT_STATUS_REFUNDED:
                $class = 'default';
                break;
        }
        return "<h4><div class=\"label label-{$class}\">$label</div></h4>";
    }
    
    public function refund($payment, $amount = null) {
        if (!$amount) {
            $amount = $payment->amount;
        }
        
        $amount = min($amount, $payment->amount - $payment->refunded);
        
        if (!$amount) {
            return;
        }
        
        if ($payment->payment_type_id != PAYMENT_TYPE_CREDIT) {
            $accountGateway = $this->createGateway($payment->account_gateway);
            $refund = $accountGateway->refund(array(
                'transactionReference' => $payment->transaction_reference,
                'amount' => $amount,
            ));
            $response = $refund->send();
            
            if ($response->isSuccessful()) {
                $payment->recordRefund($amount);
            } else {
                $this->error('Unknown', $response->getMessage(), $accountGateway);
            }
        } else {
            $payment->recordRefund($amount);
        }
    }
}
