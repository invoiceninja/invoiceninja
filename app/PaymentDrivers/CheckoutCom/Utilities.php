<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\CheckoutCom;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\SystemLog;
use Exception;
use stdClass;

trait Utilities
{
    /**
     * Map a Checkout.com source type string to Invoice Ninja GatewayType + PaymentType.
     *
     * @param  array  $_payment  The payment details array from Checkout.com
     * @return array{gateway_type_id: int, payment_type: int}
     */
    public static function resolvePaymentMeta(array $_payment): array
    {
        $sourceType = strtolower($_payment['source']['type'] ?? 'card');

        $map = [
            'ideal'      => [GatewayType::IDEAL, PaymentType::IDEAL],
            'giropay'    => [GatewayType::GIROPAY, PaymentType::GIROPAY],
            'bancontact' => [GatewayType::BANCONTACT, PaymentType::BANCONTACT],
            'p24'        => [GatewayType::PRZELEWY24, PaymentType::PRZELEWY24],
            'sofort'     => [GatewayType::SOFORT, PaymentType::SOFORT],
            'eps'        => [GatewayType::EPS, PaymentType::EPS],
            'paypal'     => [GatewayType::PAYPAL, PaymentType::PAYPAL],
            'knet'       => [GatewayType::CREDIT_CARD, PaymentType::CREDIT_CARD_OTHER],
            'multibanco' => [GatewayType::BANK_TRANSFER, PaymentType::BANK_TRANSFER],
            'applepay'   => [GatewayType::APPLE_PAY, PaymentType::CREDIT_CARD_OTHER],
            'googlepay'  => [GatewayType::CREDIT_CARD, PaymentType::CREDIT_CARD_OTHER],
        ];

        if (isset($map[$sourceType])) {
            return [
                'gateway_type_id' => $map[$sourceType][0],
                'payment_type'    => $map[$sourceType][1],
            ];
        }

        // Card payments — resolve specific card brand from scheme
        if ($sourceType === 'card') {
            return [
                'gateway_type_id' => GatewayType::CREDIT_CARD,
                'payment_type'    => PaymentType::parseCardType(strtolower($_payment['source']['scheme'] ?? '')),
            ];
        }

        // Unknown source type — fall back to credit card
        return [
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'payment_type'    => PaymentType::CREDIT_CARD_OTHER,
        ];
    }

    /**
     * Map a Checkout.com source type to its Flow SDK payment method name
     * for use in enabled_payment_methods on the session request.
     */
    public static function gatewayTypeToFlowMethod(int $gatewayType): ?string
    {
        $map = [
            GatewayType::CREDIT_CARD => 'card',
            GatewayType::IDEAL       => 'ideal',
            GatewayType::GIROPAY     => 'giropay',
            GatewayType::BANCONTACT  => 'bancontact',
            GatewayType::PRZELEWY24  => 'p24',
            GatewayType::SOFORT      => 'sofort',
            GatewayType::EPS         => 'eps',
            GatewayType::PAYPAL      => 'paypal',
            GatewayType::APPLE_PAY   => 'applepay',
        ];

        return $map[$gatewayType] ?? null;
    }

    public function getPublishableKey()
    {
        return $this->company_gateway->getConfigField('publicApiKey');
    }

    public function getParent()
    {
        return static::class == \App\PaymentDrivers\CheckoutComPaymentDriver::class ? $this : $this->checkout;
    }

    public function convertToCheckoutAmount($amount, $currency)
    {
        $cases = [
            'option_1' => ['BIF', 'DJF', 'GNF', 'ISK', 'KMF', 'XAF', 'CLF', 'XPF', 'JPY', 'PYG', 'RWF', 'KRW', 'VUV', 'VND', 'XOF'],
            'option_2' => ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'],
        ];

        // https://docs.checkout.com/resources/calculating-the-value#Calculatingthevalue-Option1:Thefullvaluefullvalue
        if (in_array($currency, $cases['option_1'])) {
            return round($amount);
        }

        // https://docs.checkout.com/resources/calculating-the-value#Calculatingthevalue-Option2:Thevaluedividedby1000valuediv1000
        if (in_array($currency, $cases['option_2'])) {
            return round($amount * 1000);
        }

        // https://docs.checkout.com/resources/calculating-the-value#Calculatingthevalue-Option3:Thevaluedividedby100valuediv100
        return round($amount * 100);
    }

    private function processSuccessfulPayment($_payment)
    {
        nlog([
            'checkout_store_card_decision' => [
                'store_card'  => $this->getParent()->payment_hash->data->store_card ?? 'NOT SET',
                'source_id'   => $_payment['source']['id'] ?? 'MISSING',
                'source_keys' => array_keys($_payment['source'] ?? []),
            ],
        ]);

        if ($this->getParent()->payment_hash->data->store_card) {
            $this->storeLocalPaymentMethod($_payment);
        }

        $meta = self::resolvePaymentMeta($_payment);

        $data = [
            'payment_method' => $_payment['source']['id'] ?? $_payment['id'],
            'payment_type' => $meta['payment_type'],
            'amount' => $this->getParent()->payment_hash->data->raw_value,
            'transaction_reference' => $_payment['id'],
            'gateway_type_id' => $meta['gateway_type_id'],
        ];

        $payment = $this->getParent()->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $_payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_CHECKOUT,
            $this->getParent()->client,
            $this->getParent()->client->company
        );

        return redirect()->route('client.payments.show', ['payment' => $this->getParent()->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($_payment, $throw_exception = true)
    {
        $error_message = '';

        nlog("checkout failure");
        nlog($_payment);

        if (is_array($_payment) && array_key_exists('status', $_payment)) {
            $error_message = $_payment['status'];
        } else {
            $error_message = 'Error processing payment.';
        }

        if (isset($_payment['actions'][0]['response_summary']) ?? false) {
            $error_message = $_payment['actions'][0]['response_summary'];
        }

        //checkout does not return a integer status code as an alias for a http status code.
        $error_code = 400;

        $this->getParent()->sendFailureMail($error_message);

        $message = [
            'server_response' => $_payment ?: 'Server did not return any response. Most likely failed before payment was created.',
            'data' => $this->getParent()->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_CHECKOUT,
            $this->getParent()->client,
            $this->getParent()->client->company,
        );

        if ($throw_exception) {
            throw new PaymentFailed($error_message, $error_code);
        }
    }

    private function processPendingPayment($_payment)
    {
        // Legacy Frames: 3DS redirect — the response contains a redirect link.
        // This must be checked first as legacy pending responses also have an 'id'.
        if (isset($_payment['_links']['redirect']['href'])) {
            try {
                return redirect($_payment['_links']['redirect']['href']);
            } catch (Exception $e) {
                return $this->getParent()->processInternallyFailedPayment($this->getParent(), $e);
            }
        }

        // Flow SDK: payment was initiated but awaits settlement (e.g. SOFORT, Multibanco).
        // Create the payment record as Pending; the webhook will mark it Completed.
        if (isset($_payment['id'])) {
            $meta = self::resolvePaymentMeta($_payment);

            $data = [
                'payment_method' => $_payment['source']['id'] ?? $_payment['id'],
                'payment_type' => $meta['payment_type'],
                'amount' => $this->getParent()->payment_hash->data->raw_value,
                'transaction_reference' => $_payment['id'],
                'gateway_type_id' => $meta['gateway_type_id'],
            ];

            $payment = $this->getParent()->createPayment($data, \App\Models\Payment::STATUS_PENDING);

            SystemLogger::dispatch(
                ['response' => $_payment, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHECKOUT,
                $this->getParent()->client,
                $this->getParent()->client->company
            );

            return redirect()->route('client.payments.show', ['payment' => $this->getParent()->encodePrimaryKey($payment->id)]);
        }

        // No redirect link and no payment ID — something went wrong
        return $this->getParent()->processInternallyFailedPayment(
            $this->getParent(),
            new Exception('Pending payment response contained no redirect link or payment ID.')
        );
    }

    private function storeLocalPaymentMethod($response)
    {
        try {
            if (empty($response['source']['id'] ?? null)) {
                session()->flash('message', ctrans('texts.payment_method_saving_failed'));
                return;
            }

            $payment_meta = new stdClass();
            $payment_meta->exp_month = (string) ($response['source']['expiry_month'] ?? '');
            $payment_meta->exp_year = (string) ($response['source']['expiry_year'] ?? '');
            $payment_meta->brand = (string) ($response['source']['scheme'] ?? '');
            $payment_meta->last4 = (string) ($response['source']['last4'] ?? '');
            $payment_meta->type = (int) GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $response['source']['id'],
                'payment_method_id' => $this->getParent()->payment_hash->data->payment_method_id ?? GatewayType::CREDIT_CARD,
            ];

            return $this->getParent()->storePaymentMethod($data, ['gateway_customer_reference' => $response['customer']['id'] ?? '']);
        } catch (Exception $e) {
            session()->flash('message', ctrans('texts.payment_method_saving_failed'));
        }
    }
}
