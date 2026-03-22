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

namespace App\PaymentDrivers\GoCardless\Jobs;

use App\Jobs\Mail\PaymentFailedMailer;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class GoCardlessWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

    public $tries = 3;

    public function __construct(private array $events, private string $company_key, private int $company_gateway_id)
    {
    }

    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company_gateway = CompanyGateway::withTrashed()->find($this->company_gateway_id);

        $company = $company_gateway->company;

        foreach ($this->events as $event) {
            nlog("GoCardless Webhook: " . $event['action']);

            nlog($event);

            /** 2026-03-20: Set the correct payment type for the payment */
            if($event['resource_type'] == 'payments' && $event['action'] == 'created' && isset($event['details']['scheme']) && isset($event['links']['payment'])) {

                $scheme = $event['details']['scheme'];

                $payment_type = match($scheme){
                    'ach' => PaymentType::ACH,
                    'autogiro' => PaymentType::DIRECT_DEBIT,
                    'bacs' => PaymentType::BACS,
                    'becs' => PaymentType::BECS,
                    'becs_nz' => PaymentType::DIRECT_DEBIT,
                    'betalingsservice' => PaymentType::DIRECT_DEBIT,
                    'faster_payments' => PaymentType::INSTANT_BANK_PAY,
                    'pad' => PaymentType::ACSS,
                    'pay_to' => PaymentType::DIRECT_DEBIT,
                    'sepa_core' => PaymentType::SEPA,
                    default => PaymentType::DIRECT_DEBIT,
                };

                $payment = Payment::query()
                            ->where('transaction_reference', $event['links']['payment'])
                            ->where('company_id', $company->id)
                            ->first();

                if($payment){
                    $payment->type_id = $payment_type;
                    $payment->saveQuietly();
                }

            }

            if (
                ($event['resource_type'] == 'payments' && $event['action'] == 'confirmed')
                || $event['action'] === 'paid_out') {
                nlog('Searching for transaction reference');

                $payment = Payment::query()
                    ->where('transaction_reference', $event['links']['payment'])
                    ->where('company_id', $company->id)
                    ->first();

                if ($payment) {
                    $payment->status_id = Payment::STATUS_COMPLETED;
                    $payment->save();
                    nlog('GoCardless completed');
                } else {
                    nlog('I was unable to find the payment for this reference');
                }
                //finalize payments on invoices here.
            }

            if ($event['action'] === 'failed' && array_key_exists('payment', $event['links'])) {
                $payment = Payment::query()
                    ->where('transaction_reference', $event['links']['payment'])
                    ->where('company_id', $company->id)
                    ->first();

                if ($payment) {
                    
                    if ($payment->status_id == Payment::STATUS_PENDING) {
                        $payment->service()->deletePayment();
                    }

                    $payment->status_id = Payment::STATUS_FAILED;
                    $payment->save();

                    $payment_hash = PaymentHash::where('payment_id', $payment->id)->first();
                    $error = '';

                    if (isset($event['details']['description'])) {
                        $error = $event['details']['description'];
                    }

                    PaymentFailedMailer::dispatch(
                        $payment_hash,
                        $payment->client->company,
                        $payment->client,
                        $error
                    );
                }
            }


        }
    }

    public function middleware()
    {
        return [new WithoutOverlapping("gocardless-webhook-{$this->company_key}-{$this->company_gateway_id}")];
    }
}