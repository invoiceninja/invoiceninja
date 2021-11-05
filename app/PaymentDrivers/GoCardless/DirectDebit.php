<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers\GoCardless;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use Illuminate\Http\Request;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

class DirectDebit implements MethodInterface
{
    use MakesHash;

    protected GoCardlessPaymentDriver $go_cardless;

    public function __construct(GoCardlessPaymentDriver $go_cardless)
    {
        $this->go_cardless = $go_cardless;

        $this->go_cardless->init();
    }

    /**
     * Handle authorization for Direct Debit.
     *
     * @param array $data
     * @return Redirector|RedirectResponse|void
     */
    public function authorizeView(array $data)
    {
        $session_token = \Illuminate\Support\Str::uuid()->toString();

        try {
            $redirect = $this->go_cardless->gateway->redirectFlows()->create([
                'params' => [
                    'session_token' => $session_token,
                    'success_redirect_url' => route('client.payment_methods.confirm', [
                        'method' => GatewayType::DIRECT_DEBIT,
                        'session_token' => $session_token,
                    ]),
                    'prefilled_customer' => [
                        'given_name' => auth('contact')->user()->first_name,
                        'family_name' => auth('contact')->user()->last_name,
                        'email' => auth('contact')->user()->email,
                        'address_line1' => auth('contact')->user()->client->address1,
                        'city' => auth('contact')->user()->client->city,
                        'postal_code' => auth('contact')->user()->client->postal_code,
                    ],
                ],
            ]);

            return redirect(
                $redirect->redirect_url
            );
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulAuthorization($exception);
        }
    }

    /**
     * Handle unsuccessful authorization.
     *
     * @param Exception $exception
     * @throws PaymentFailed
     * @return void
     */
    public function processUnsuccessfulAuthorization(\Exception $exception): void
    {
        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }

    /**
     * Handle authorization response for Direct Debit.
     *
     * @param Request $request
     * @return RedirectResponse|void
     */
    public function authorizeResponse(Request $request)
    {
        try {
            $redirect_flow = $this->go_cardless->gateway->redirectFlows()->complete(
                $request->redirect_flow_id,
                ['params' => [
                    'session_token' => $request->session_token
                ]],
            );

            $payment_meta = new \stdClass;
            $payment_meta->brand = ctrans('texts.payment_type_direct_debit');
            $payment_meta->type = GatewayType::DIRECT_DEBIT;
            $payment_meta->state = 'authorized';

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $redirect_flow->links->mandate,
                'payment_method_id' => GatewayType::DIRECT_DEBIT,
            ];

            $payment_method = $this->go_cardless->storeGatewayToken($data, ['gateway_customer_reference' => $redirect_flow->links->customer]);

            return redirect()->route('client.payment_methods.show', $payment_method->hashed_id);
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulAuthorization($exception);
        }
    }

    /**
     * Payment view for Direct Debit.
     *
     * @param array $data
     * @return View
     */
    public function paymentView(array $data): View
    {
        $data['gateway'] = $this->go_cardless;
        $data['amount'] = $this->go_cardless->convertToGoCardlessAmount($data['total']['amount_with_fee'], $this->go_cardless->client->currency()->precision);
        $data['currency'] = $this->go_cardless->client->getCurrencyCode();

        return render('gateways.gocardless.direct_debit.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $token = ClientGatewayToken::find(
            $this->decodePrimaryKey($request->source)
        )->firstOrFail();

        $this->go_cardless->ensureMandateIsReady($token);

        try {
            $payment = $this->go_cardless->gateway->payments()->create([
                'params' => [
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'metadata' => [
                        'payment_hash' => $this->go_cardless->payment_hash->hash,
                    ],
                    'links' => [
                        'mandate' => $token->token,
                    ],
                ],
            ]);


            if ($payment->status === 'pending_submission') {
                return $this->processPendingPayment($payment, ['token' => $token->hashed_id]);
            }

            return $this->processUnsuccessfulPayment($payment);
        } catch (\Exception $exception) {
            throw new PaymentFailed($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Handle pending payments for Direct Debit.
     *
     * @param ResourcesPayment $payment
     * @param array $data
     * @return RedirectResponse
     */
    public function processPendingPayment(\GoCardlessPro\Resources\Payment $payment, array $data = [])
    {
        $data = [
            'payment_method' => $data['token'],
            'payment_type' => PaymentType::DIRECT_DEBIT,
            'amount' => $this->go_cardless->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id,
            'gateway_type_id' => GatewayType::DIRECT_DEBIT,
        ];

        $payment = $this->go_cardless->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->go_cardless->encodePrimaryKey($payment->id)]);
    }

    /**
     * Process unsuccessful payments for Direct Debit.
     *
     * @param ResourcesPayment $payment
     * @return never
     */
    public function processUnsuccessfulPayment(\GoCardlessPro\Resources\Payment $payment)
    {
        PaymentFailureMailer::dispatch($this->go_cardless->client, $payment->status, $this->go_cardless->client->company, $this->go_cardless->payment_hash->data->amount_with_fee);

        PaymentFailureMailer::dispatch(
            $this->go_cardless->client,
            $payment,
            $this->go_cardless->client->company,
            $payment->amount
        );

        $message = [
            'server_response' => $payment,
            'data' => $this->go_cardless->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }
}
