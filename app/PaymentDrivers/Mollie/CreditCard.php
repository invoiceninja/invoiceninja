<?php

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

class CreditCard
{
    /**
     * @var MolliePaymentDriver
     */
    protected $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    /**
     * Show the page for credit card payments.
     * 
     * @param array $data 
     * @return Factory|View 
     */
    public function paymentView(array $data)
    {
        $data['gateway'] = $this->mollie;

        return render('gateways.mollie.credit_card.pay', $data);
    }

    /**
     * Create a payment object.
     * 
     * @param PaymentResponseRequest $request 
     * @return mixed 
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $amount = number_format((float) $this->mollie->payment_hash->data->amount_with_fee, 2, '.', '');

        try {
            $payment = $this->mollie->gateway->payments->create([
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $amount,
                ],
                'description' => \sprintf('Hash: %s', $this->mollie->payment_hash->hash),
                'redirectUrl' => 'https://webshop.example.org/order/12345/',
                'webhookUrl'  => 'https://webshop.example.org/mollie-webhook/',
                'cardToken' => $request->token,
            ]);

            if ($payment->status === 'paid') {
                $this->mollie->logSuccessfulGatewayResponse(
                    ['response' => $payment, 'data' => $this->mollie->payment_hash],
                    SystemLog::TYPE_MOLLIE
                );

                $this->processSuccessfulPayment($payment);
            }

            if ($payment->status === 'open') {
                // Handle redirect payment
            }

            dd($payment);

        } catch (\Exception $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    protected function processSuccessfulPayment(\Mollie\Api\Resources\Payment $payment)
    {
        // Check if storing credit card is enabled

        $payment_hash = $this->mollie->payment_hash;

        $data = [
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total,
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'transaction_reference' => $payment->id,
        ];

        $payment_record = $this->mollie->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->mollie->encodePrimaryKey($payment_record->id)]);
    }
}
