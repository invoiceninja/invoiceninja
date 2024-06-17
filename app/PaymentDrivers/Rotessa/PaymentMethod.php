<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Rotessa;

use Carbon\Carbon;
use App\Models\Payment;
use App\Models\SystemLog;
use Illuminate\View\View;
use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use Illuminate\Http\RedirectResponse;
use App\PaymentDrivers\Rotessa\Resources\Customer;
use App\PaymentDrivers\RotessaPaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\Rotessa\Resources\Transaction;
use App\PaymentDrivers\Rotessa\DataProviders\Frequencies;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use App\Exceptions\Ninja\ClientPortalAuthorizationException;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class PaymentMethod implements MethodInterface
{
    protected RotessaPaymentDriver $rotessa;

    public function __construct(RotessaPaymentDriver $rotessa)
    {
        $this->rotessa = $rotessa;
        $this->rotessa->init();
    }

    /**
     * Show the authorization page for Rotessa.
     *
     * @param array $data
     * @return \Illuminate\View\View         
     */
    public function authorizeView(array $data): View
    {
        $data['contact'] = collect($data['client']->contacts->firstWhere('is_primary', 1)->toArray())->merge([
            'home_phone' => $data['client']->phone, 
            'custom_identifier' => $data['client']->number,
            'name' => $data['client']->name,
            'id' => null
        ] )->all();
        $data['gateway'] = $this->rotessa;
        $data['gateway_type_id'] =  $data['client']->country->iso_3166_2 == 'US' ?  GatewayType::BANK_TRANSFER : (  $data['client']->country->iso_3166_2 == 'CA' ? GatewayType::ACSS : (int) request('method'));
        $data['account'] = [
            'routing_number' => $data['client']->routing_id,
            'country' => $data['client']->country->iso_3166_2
        ];
        $data['address'] = collect($data['client']->toArray())->merge(['country' => $data['client']->country->iso_3166_2 ])->all();
        
        return view('rotessa::bank_transfer.authorize', $data);
    }
    /**
     * Handle the authorization page for Rotessa.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'gateway_type_id' => ['required','integer'],
                'country' => ['required'],
                'name' => ['required'],
                'address_1' => ['required'],
                'address_2' => ['required'],
                'city' => ['required'],
                'email' => ['required','email:filter'],
                'province_code' => ['required','size:2','alpha'],
                'postal_code' => ['required'],
                'authorization_type' => ['required'],
                'account_number' => ['required'],
                'bank_name' => ['required'],
                'phone' => ['required'],
                'home_phone' => ['required'],
                'bank_account_type'=>['required_if:country,US'],
                'routing_number'=>['required_if:country,US'],
                'institution_number'=>['required_if:country,CA','numeric'],
                'transit_number'=>['required_if:country,CA','numeric'],
                'custom_identifier'=>['required_without:customer_id'],
                'customer_id'=>['required_without:custom_identifier','integer'],
            ]);
            $customer = new Customer(
                $request->merge(['custom_identifier' => $request->input('id') ] +
                ['address' => $request->only('address_1','address_2','city','postal_code','province_code','country') ])->all() 
            );
            $this->rotessa->findOrCreateCustomer($customer->resolve());
            
            return redirect()->route('client.payment_methods.index')->withMessage(ctrans('texts.payment_method_added'));

        } catch (\Throwable $e) {
            return $this->rotessa->processInternallyFailedPayment($this->rotessa, new ClientPortalAuthorizationException( get_class( $e) . " :  {$e->getMessage()}", (int)  $e->getCode() ));
        }

        return back()->withMessage(ctrans('texts.unable_to_verify_payment_method'));
    }

    /**
     * Payment view for the Rotessa.
     *
     * @param array $data
     * @return \Illuminate\View\View         
     */
    public function paymentView(array $data): View
    {
        $data['gateway'] = $this->rotessa;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['due_date'] = date('Y-m-d', min(max(strtotime($data['invoices']->max('due_date')), strtotime('now')), strtotime('+1 day')));
        $data['process_date'] = $data['due_date'];
        $data['currency'] = $this->rotessa->client->getCurrencyCode();
        $data['frequency'] = Frequencies::getOnePayment();
        $data['installments'] = 1;
        $data['invoice_nums'] = $data['invoices']->pluck('invoice_number')->join(', '); 
        return view('rotessa::bank_transfer.pay', $data );
    }

    /**
     * Handle payments page for Rotessa.
     *
     * @param PaymentResponseRequest $request
     * @return void
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $response= null;
        $customer = null;
        try {
            $request->validate([
                'source' => ['required','string','exists:client_gateway_tokens,token'],
                'amount' => ['required','numeric'],
                'token_id' => ['required','integer','exists:client_gateway_tokens,id'],
                'process_date'=> ['required','date','after_or_equal:today'],
            ]);
            $customer = ClientGatewayToken::query()
                ->where('company_gateway_id', $this->rotessa->company_gateway->id)
                ->where('client_id', $this->rotessa->client->id)
                ->where('id', (int) $request->input('token_id'))
                ->where('token', $request->input('source'))
                ->first();
            if(!$customer) throw new \Exception('Client gateway token not found!', 605);

            $transaction = new Transaction($request->only('frequency' ,'installments','amount','process_date','comment'));
            $transaction->additional(['customer_id' => $customer->gateway_customer_reference]);
            $transaction = array_filter( $transaction->resolve());
            $response = $this->rotessa->gateway->capture($transaction)->send();
            if(!$response->isSuccessful()) throw new \Exception($response->getMessage(), (int) $response->getCode()); 
            
           return  $this->processPendingPayment($response->getParameter('id'), (float) $response->getParameter('amount'), (int) $customer->gateway_type_id , $customer->token);
        } catch(\Throwable $e) {
            $this->processUnsuccessfulPayment( new InvalidResponseException($e->getMessage(), (int) $e->getCode()) );
        }
    }

    public function processPendingPayment($payment_id, float $amount, int $gateway_type_id, $payment_method )
    {
        $data = [
            'payment_method' => $payment_method,
            'payment_type' => $gateway_type_id,
            'amount' => $amount,
            'transaction_reference' =>$payment_id,
            'gateway_type_id' => $gateway_type_id,
        ];
        $payment = $this->rotessa->createPayment($data, Payment::STATUS_PENDING);
        SystemLogger::dispatch(
            [ 'data' => $data ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            880,
            $this->rotessa->client,
            $this->rotessa->client->company,
        );

        return redirect()->route('client.payments.show', [ 'payment' => $this->rotessa->encodePrimaryKey($payment->id) ]);
    }

    /**
     * Handle unsuccessful payment for Rotessa.
     *
     * @param Exception $exception
     * @throws PaymentFailed
     * @return void
     */
    public function processUnsuccessfulPayment(\Exception $exception): void
    {
        $this->rotessa->sendFailureMail($exception->getMessage());

        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            880,
            $this->rotessa->client,
            $this->rotessa->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }
}
