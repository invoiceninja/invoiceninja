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

namespace App\PaymentDrivers\PayFast;

use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentCompletedWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public array $data, public string $company_key, public int $company_gateway_id) {}
    //   'm_payment_id' => 'aobgUGfYHQXCdFdXYyfXiEolPOOYIdbb',
    //   'pf_payment_id' => '2579',
    //   'payment_status' => 'COMPLETE',
    //   'item_name' => 'Invoices: ["0081"]',
    //   'item_description' => 'Credit Card Pre Authorization',
    //   'amount_gross' => '1481.55',
    //   'amount_fee' => '-68.75',
    //   'amount_net' => '1412.80',
    //   'custom_str1' => NULL,
    //   'custom_str2' => NULL,
    //   'custom_str3' => NULL,
    //   'custom_str4' => NULL,
    //   'custom_str5' => NULL,
    //   'custom_int1' => NULL,
    //   'custom_int2' => NULL,
    //   'custom_int3' => NULL,
    //   'custom_int4' => NULL,
    //   'custom_int5' => NULL,
    //   'name_first' => NULL,
    //   'name_last' => NULL,
    //   'email_address' => NULL,
    //   'merchant_id' => '10023100',
    //   'token' => '8e1bf463-0c75-4f9c-836b-9bd02de14fc4',
    //   'billing_date' => '2025-06-16',
    //   'signature' => 'acfddcf33967679bcc743532dfef9a89',
    //   'q' => '/payment_notification_webhook/M2zB4QN6EabKLGV319vzqXFy0J2Xvxer/4w9aAOdvMR/7LDdwRb1YK',

    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company = Company::query()->where('company_key', $this->company_key)->first();

        $existing = Payment::query()
            ->where('company_id', $company->id)
            ->where('transaction_reference', $this->data['pf_payment_id'])
            ->first();

        if ($existing) {
            return;
        }

        $payment_hash = PaymentHash::where('hash', $this->data['m_payment_id'])->first();

        if (! $payment_hash) {
            nlog('Payfast:: payment_hash not found for m_payment_id ' . $this->data['m_payment_id']);
            return;
        }

        $expected = (float) $payment_hash->data->amount_with_fee;
        $received = (float) $this->data['amount_gross'];

        if (abs($received - $expected) > 0.02) {
            nlog('Payfast:: Amount mismatch');
            return;
        }

        $company_gateway = CompanyGateway::query()
            ->where('company_id', $company->id)
            ->where('id', $this->company_gateway_id)
            ->first();

        $driver = $company_gateway->driver($payment_hash->fee_invoice->client)->init();
        $driver->setPaymentHash($payment_hash);

        $status = $this->data['payment_status'] ?? null;

        if ($status !== 'COMPLETE') {
            $this->processFailure($driver, $status);
            return;
        }

        $payment_hash->data = array_merge((array) $payment_hash->data, [
            'server_response' => $this->data,
            'payment_hash' => $this->data['m_payment_id'],
        ]);
        $payment_hash->save();

        $payment_record = [
            'amount' => $this->data['amount_gross'],
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'transaction_reference' => $this->data['pf_payment_id'],
            'idempotency_key' => $this->data['pf_payment_id'] . $payment_hash->hash,
        ];

        $driver->logSuccessfulGatewayResponse(
            ['response' => $this->data, 'data' => $payment_hash->data],
            SystemLog::TYPE_PAYFAST,
        );

        $driver->createPayment($payment_record, Payment::STATUS_COMPLETED);

        $this->maybeStoreToken($driver, $payment_hash);
    }

    /**
     * Persist the PayFast token as a ClientGatewayToken when the client
     * opted into "save card" on the checkout form.
     */
    private function maybeStoreToken($driver, PaymentHash $payment_hash): void
    {
        $store_card = $payment_hash->data->store_card ?? false;
        $token = $this->data['token'] ?? null;

        if (! $store_card || ! $token) {
            return;
        }

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = 'xx';
        $payment_meta->exp_year = 'xx';
        $payment_meta->brand = 'CC';
        $payment_meta->last4 = 'xxxx';
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $driver->storeGatewayToken([
            'token' => $token,
            'payment_method_id' => GatewayType::CREDIT_CARD,
            'payment_meta' => $payment_meta,
        ], []);
    }

    private function processFailure($driver, ?string $status): void
    {
        $reason = $this->data['cancellation_reason']
            ?? ('PayFast payment status: ' . ($status ?? 'unknown'));

        $driver->sendFailureMail($reason);

        SystemLogger::dispatch(
            ['server_response' => $this->data, 'data' => $driver->payment_hash->data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYFAST,
            $driver->client,
            $driver->client->company,
        );
    }
}
