<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe\Jobs;

use App\Jobs\Mail\PaymentFailedMailer;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentIntentFailureWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Utilities;

    public $tries = 1; //number of retries

    public $deleteWhenMissingModels = true;

    public $stripe_request;

    public $company_key;

    private $company_gateway_id;

    public $payment_completed = false;

    public function __construct($stripe_request, $company_key, $company_gateway_id)
    {
        $this->stripe_request = $stripe_request;
        $this->company_key = $company_key;
        $this->company_gateway_id = $company_gateway_id;
    }

    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company = Company::where('company_key', $this->company_key)->first();

        foreach ($this->stripe_request as $transaction) {
            if (array_key_exists('payment_intent', $transaction)) {
                $payment = Payment::query()
                        ->where('company_id', $company->id)
                        ->where(function ($query) use ($transaction) {
                            $query->where('transaction_reference', $transaction['payment_intent'])
                                  ->orWhere('transaction_reference', $transaction['id']);
                        })
                        ->first();
            } else {
                $payment = Payment::query()
                        ->where('company_id', $company->id)
                        ->where('transaction_reference', $transaction['id'])
                        ->first();
            }

            if ($payment) {
                $client = $payment->client;

                if ($payment->status_id == Payment::STATUS_PENDING) {
                    $payment->service()->deletePayment();
                }

                $payment->status_id = Payment::STATUS_FAILED;
                $payment->save();

                $payment_hash = PaymentHash::where('payment_id', $payment->id)->first();

                if ($payment_hash) {
                    $error = ctrans('texts.client_payment_failure_body', [
                        'invoice' => implode(',', $payment->invoices->pluck('number')->toArray()),
                        'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total, ]);
                } else {
                    $error = 'Payment for '.$payment->client->present()->name()." for {$payment->amount} failed";
                }

                if (array_key_exists('failure_message', $transaction)) {
                    $error .= "\n\n".$transaction['failure_message'];
                }

                PaymentFailedMailer::dispatch(
                        $payment_hash,
                        $client->company,
                        $client,
                        $error
                    );
            }
        }
    }
}
