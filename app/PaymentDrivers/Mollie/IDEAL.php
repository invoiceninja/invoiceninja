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

namespace App\PaymentDrivers\Mollie;

use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use Mollie\Api\Exceptions\ApiException;

class IDEAL extends MolliePaymentMethod implements MethodInterface, LivewireMethodInterface
{
    protected const MOLLIE_PAYMENT_METHOD = 'ideal';

    protected const GATEWAY_TYPE_ID = GatewayType::IDEAL;

    protected const PAYMENT_TYPE_ID = PaymentType::IDEAL;

    protected const AUTHORIZE_VIEW_TEMPLATE = 'gateways.mollie.ideal.authorize';

    /** @inheritDoc */
    public function paymentView(array $data)
    {
        $this->mollie->payment_hash
            ->withData('gateway_type_id', GatewayType::IDEAL)
            ->withData('client_id', $this->mollie->client->id);

        try {
            $data = [
                'method' => 'ideal',
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $this->mollie->convertToMollieAmount((float)$this->mollie->payment_hash->data->amount_with_fee),
                ],
                'description' => \sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($data['invoices'])->pluck('invoice_number')->toArray())),
                'redirectUrl' => route('client.payments.response', [
                    'company_gateway_id' => $this->mollie->company_gateway->id,
                    'payment_hash' => $this->mollie->payment_hash->hash,
                    'payment_method_id' => GatewayType::IDEAL,
                ]),
                'webhookUrl' => $this->mollie->company_gateway->webhookUrl(),
                'metadata' => [
                    'client_id' => $this->mollie->client->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                    'gateway_type_id' => GatewayType::IDEAL,
                    'payment_type_id' => PaymentType::IDEAL,
                ],
            ];

            if ($this->mollie->company_gateway->token_billing == 'always') {
                // Check if a mollie CustomerId already exists for this client, if so, use that
                $gateway_customer_reference = null;
                if ($this->mollie->client->gateway_tokens->count() > 0) {
                    $gateway_customer_reference = $this->mollie->client->gateway_tokens->first()->gateway_customer_reference;
                } else {
                    // Only if a ClientGatewayToken a.k.a. mandate doesn't exist, we need to set the sequenceType to first to create one
                    $data['sequenceType'] = 'first';
                }
                if (!$gateway_customer_reference) {
                    $customer = $this->mollie->gateway->customers->create([
                        'name' => $this->mollie->client->name,
                        'email' => $this->mollie->client->present()->email(),
                        'metadata' => [
                            'id' => $this->mollie->client->hashed_id,
                        ],
                    ]);
                    $gateway_customer_reference = $customer->id;
                }

                $data['customerId'] = $gateway_customer_reference;

                $this->mollie->payment_hash
                    ->withData('mollieCustomerId', $gateway_customer_reference)
                    ->withData('shouldStoreToken', true);
            }

            try {
                $molliePayment = $this->mollie->gateway->payments->create($data);
            } catch (ApiException $e) {
                if (preg_match('/method selected.*not accept recurring payments/', $e->getMessage())) {
                    // Can't create mandate, log
                    SystemLogger::dispatch(
                        ['error' => 'Could not create mandate upon iDeal payment. Check if all required payment methods are enabled in Mollie (e.g. SEPA Direct Debit).',
                            'exception' => $e->getMessage()],
                        SystemLog::CATEGORY_GATEWAY_RESPONSE,
                        SystemLog::EVENT_GATEWAY_ERROR,
                        SystemLog::TYPE_MOLLIE,
                        $this->mollie->client,
                        $this->mollie->client->company,
                    );

                    // Try again without creating mandate
                    unset($data['customerId']);
                    unset($data['sequenceType']);
                    $molliePayment = $this->mollie->gateway->payments->create($data);
                } else {
                    // Fail in the usual way
                    throw $e;
                }
            }

            $this->mollie->payment_hash->withData('transaction_reference', $molliePayment->id);

            return redirect($molliePayment->getCheckoutUrl());
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulPayment($exception);
        }
    }

}
