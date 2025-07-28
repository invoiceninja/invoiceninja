<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\PayFast;

use App\Models\Company;
use App\Models\Payment;
use App\Libraries\MultiDB;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Bus\Queueable;
use App\Models\CompanyGateway;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompletedWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $data, public string $company_key, public int $company_gateway_id){}
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
        nlog("PaymentCompletedWebhook");
        nlog(now()->format('Y-m-d H:i:s'));
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company = Company::query()->where('company_key', $this->company_key)->first();

        $p = Payment::query()
            ->where('company_id', $company->id)
            ->where('transaction_reference', $this->data['pf_payment_id'])
            ->first();

        if($p){
            nlog("payment found returning");
            return;
        }

        nlog("yolo");
        $payment_hash = PaymentHash::where('hash', $this->data['m_payment_id'])->first();
                
        $company_gateway = CompanyGateway::query()->where('company_id', $company->id)->where('id', $this->company_gateway_id)->first();
        $driver = $company_gateway->driver($payment_hash->fee_invoice->client)->init();
        $driver->setPaymentHash($payment_hash);
        
        $payment_record = [];
        $payment_record['amount'] =  $this->data['amount_gross'];
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $this->data['pf_payment_id'];
        $payment_record['idempotency_key'] = $this->data['pf_payment_id'].$payment_hash->hash;

        $payment = $driver->createPayment($payment_record, Payment::STATUS_COMPLETED);
        
    }
}