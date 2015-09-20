<?php namespace App\Services;

use URL;
use DateTime;
use Event;
use Omnipay;
use Session;
use CreditCard;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Country;
use App\Models\AccountGatewayToken;
use App\Ninja\Repositories\AccountRepository;
use App\Events\InvoicePaid;

class PaymentService {

    public $lastError;

    public function __construct(AccountRepository $accountRepo)
    {
        $this->accountRepo = $accountRepo;
    }

    public function createGateway($accountGateway)
    {
        $gateway = Omnipay::create($accountGateway->gateway->provider);
        $config = json_decode($accountGateway->config);

        foreach ($config as $key => $val) {
            if (!$val) {
                continue;
            }

            $function = "set".ucfirst($key);
            $gateway->$function($val);
        }

        if ($accountGateway->gateway->id == GATEWAY_DWOLLA) {
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

    public function getPaymentDetails($invitation, $input = null)
    {
        $invoice = $invitation->invoice;
        $account = $invoice->account;
        $key = $invoice->account_id.'-'.$invoice->invoice_number;
        $currencyCode = $invoice->client->currency ? $invoice->client->currency->code : ($invoice->account->currency ? $invoice->account->currency->code : 'USD');

        if ($input) {
            $data = self::convertInputForOmnipay($input);
            Session::put($key, $data);
        } elseif (Session::get($key)) {
            $data = Session::get($key);
        } else {
            $data = $this->createDataForClient($invitation);
        }

        $card = new CreditCard($data);

        return [
            'amount' => $invoice->getRequestedAmount(),
            'card' => $card,
            'currency' => $currencyCode,
            'returnUrl' => URL::to('complete'),
            'cancelUrl' => $invitation->getLink(),
            'description' => trans('texts.' . $invoice->getEntityType()) . " {$invoice->invoice_number}",
            'transactionId' => $invoice->invoice_number,
            'transactionType' => 'Purchase',
        ];
    }

    private function convertInputForOmnipay($input)
    {
        $data = [
            'firstName' => $input['first_name'],
            'lastName' => $input['last_name'],
            'number' => $input['card_number'],
            'expiryMonth' => $input['expiration_month'],
            'expiryYear' => $input['expiration_year'],
            'cvv' => $input['cvv'],
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
            'billingCountry' => $client->country->iso_3166_2,
            'billingPhone' => $contact->phone,
            'shippingAddress1' => $client->address1,
            'shippingAddress2' => $client->address2,
            'shippingCity' => $client->city,
            'shippingPostcode' => $client->postal_code,
            'shippingState' => $client->state,
            'shippingCountry' => $client->country->iso_3166_2,
            'shippingPhone' => $contact->phone,
        ];
    }

    public function createToken($gateway, $details, $accountGateway, $client, $contactId)
    {
        $tokenResponse = $gateway->createCard($details)->send();
        $cardReference = $tokenResponse->getCardReference();

        if ($cardReference) {
            $token = AccountGatewayToken::where('client_id', '=', $client->id)
            ->where('account_gateway_id', '=', $accountGateway->id)->first();

            if (!$token) {
                $token = new AccountGatewayToken();
                $token->account_id = $client->account->id;
                $token->contact_id = $contactId;
                $token->account_gateway_id = $accountGateway->id;
                $token->client_id = $client->id;
            }

            $token->token = $cardReference;
            $token->save();
        } else {
            $this->lastError = $tokenResponse->getMessage();
        }

        return $cardReference;
    }

    public function createPayment($invitation, $ref, $payerId = null)
    {
        $invoice = $invitation->invoice;
        $accountGateway = $invoice->client->account->getGatewayByType(Session::get('payment_type'));

        // sync pro accounts
        if ($invoice->account->account_key == NINJA_ACCOUNT_KEY 
                && $invoice->amount == PRO_PLAN_PRICE) {
            $account = Account::with('users')->find($invoice->client->public_id);
            if ($account->pro_plan_paid && $account->pro_plan_paid != '0000-00-00') {
                $date = DateTime::createFromFormat('Y-m-d', $account->pro_plan_paid);
                $account->pro_plan_paid = $date->modify('+1 year')->format('Y-m-d');
            } else {
                $account->pro_plan_paid = date_create()->format('Y-m-d');
            }
            $account->save();

            $user = $account->users()->first();
            $this->accountRepo->syncAccounts($user->id, $account->pro_plan_paid);
        }

        $payment = Payment::createNew($invitation);
        $payment->invitation_id = $invitation->id;
        $payment->account_gateway_id = $accountGateway->id;
        $payment->invoice_id = $invoice->id;
        $payment->amount = $invoice->getRequestedAmount();
        $payment->client_id = $invoice->client_id;
        $payment->contact_id = $invitation->contact_id;
        $payment->transaction_reference = $ref;
        $payment->payment_date = date_create()->format('Y-m-d');
        
        if ($payerId) {
            $payment->payer_id = $payerId;
        }

        $payment->save();

        Event::fire(new InvoicePaid($payment));

        return $payment;
    }

    public function autoBillInvoice($invoice)
    {
        $client = $invoice->client;
        $account = $invoice->account;
        $invitation = $invoice->invitations->first();
        $accountGateway = $account->getGatewayConfig(GATEWAY_STRIPE);

        if (!$invitation || !$accountGateway) {
            return false;
        }

        // setup the gateway/payment info
        $gateway = $this->createGateway($accountGateway);
        $details = $this->getPaymentDetails($invitation);
        $details['cardReference'] = $client->getGatewayToken();

        // submit purchase/get response
        $response = $gateway->purchase($details)->send();
        $ref = $response->getTransactionReference();

        // create payment record
        return $this->createPayment($invitation, $ref);
    }
}
