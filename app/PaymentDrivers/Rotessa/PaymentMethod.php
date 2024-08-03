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
use App\Models\Client;
use App\Models\Payment;
use App\Models\SystemLog;
use Illuminate\View\View;
use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use App\DataProviders\Frequencies;
use App\Models\ClientGatewayToken;
use Illuminate\Http\RedirectResponse;
use App\PaymentDrivers\RotessaPaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\Rotessa\Resources\Customer;
use App\PaymentDrivers\Rotessa\Resources\Transaction;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Common\Exception\InvalidResponseException;
use App\Exceptions\Ninja\ClientPortalAuthorizationException;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class PaymentMethod implements MethodInterface
{

    public function __construct(protected RotessaPaymentDriver $rotessa)
    {
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
        $data['gateway_type_id'] =   GatewayType::ACSS ;
        $data['account'] = [
            'routing_number' => $data['client']->routing_id,
            'country' => $data['client']->country->iso_3166_2
        ];
        $data['address'] = collect($data['client']->toArray())->merge(['country' => $data['client']->country->iso_3166_2 ])->all();
        
        return render('gateways.rotessa.bank_transfer.authorize',  $data );
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
                // 'address_2' => ['required'],
                'city' => ['required'],
                'email' => ['required','email:filter'],
                'province_code' => ['required','size:2','alpha'],
                'postal_code' => ['required'],
                'authorization_type' => ['required'],
                'account_number' => ['required'],
                'bank_name' => ['required'],
                'phone' => ['required'],
                'home_phone' => ['required','size:10'],
                'bank_account_type'=>['required_if:country,US'],
                'routing_number'=>['required_if:country,US'],
                'institution_number'=>['required_if:country,CA','numeric'],
                'transit_number'=>['required_if:country,CA','numeric'],
                'custom_identifier'=>['required_without:customer_id'],
                'customer_id'=>['required_without:custom_identifier','integer'],
            ]);
            $customer = new Customer(  ['address' => $request->only('address_1','address_2','city','postal_code','province_code','country'), 'custom_identifier' => $request->input('custom_identifier') ] + $request->all());

            $this->rotessa->findOrCreateCustomer($customer->resolve());
            
            return redirect()->route('client.payment_methods.index')->withMessage(ctrans('texts.payment_method_added'));

        } catch (\Throwable $e) {
            return $this->rotessa->processInternallyFailedPayment($this->rotessa, new ClientPortalAuthorizationException( get_class( $e) . " :  {$e->getMessage()}", (int)  $e->getCode() ));
        }

        // return back()->withMessage(ctrans('texts.unable_to_verify_payment_method'));
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
        return render('gateways.rotessa.bank_transfer.pay', $data );
    }

    /**
     * Handle payments page for Rotessa.
     *
     * @param PaymentResponseRequest $request
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {

        $response= null;
        $customer = null;

        try {
            $request->validate([
                'source' => ['required','string','exists:client_gateway_tokens,token'],
                'amount' => ['required','numeric'],
                'process_date'=> ['required','date','after_or_equal:today'],
            ]);
            $customer = ClientGatewayToken::query()
                ->where('company_gateway_id', $this->rotessa->company_gateway->id)
                ->where('client_id', $this->rotessa->client->id)
                ->where('is_deleted', 0)
                ->where('token', $request->input('source'))
                ->first();

            if(!$customer) throw new \Exception('Client gateway token not found!',  SystemLog::TYPE_ROTESSA);

            $transaction = new Transaction($request->only('frequency' ,'installments','amount','process_date') + ['comment' => $this->rotessa->getDescription(false) ]);
            $transaction->additional(['customer_id' => $customer->gateway_customer_reference]);
            $transaction = array_filter( $transaction->resolve());
            $response = $this->rotessa->gatewayRequest('post','transaction_schedules', $transaction);
                        
            if($response->failed()) 
                $response->throw(); 
            
            $response = $response->json();
            nlog($response);
           return  $this->processPendingPayment($response['id'], (float) $response['amount'], PaymentType::ACSS , $customer->token);
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
            SystemLog::TYPE_ROTESSA,
            $this->rotessa->client,
            $this->rotessa->client->company,
        );

        return redirect()->route('client.payments.show', [ 'payment' => $payment->hashed_id ]);
    }

    /**
     * Handle unsuccessful payment for Rotessa.
     *
     * @param \Exception $exception
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
            SystemLog::TYPE_ROTESSA,
            $this->rotessa->client,
            $this->rotessa->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }
}
