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

namespace App\PaymentDrivers\Stripe\Jobs;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ChargeRefunded implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

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
        nlog($this->stripe_request);

        $company = Company::query()->where('company_key', $this->company_key)->first();

        $source = $this->stripe_request['object'];
        $charge_id = $source['id'];
        $amount_refunded = $source['amount_refunded'] ?? 0;

        $payment_hash_key = $source['metadata']['payment_hash'] ?? null;

        $company_gateway = CompanyGateway::query()->find($this->company_gateway_id);
        $payment_hash = PaymentHash::query()->where('hash', $payment_hash_key)->first();

        $stripe_driver = $company_gateway->driver()->init();

        $stripe_driver->payment_hash = $payment_hash;

        /** @var \App\Models\Payment $payment **/
        $payment = Payment::query()
                         ->withTrashed()
                         ->where('company_id', $company->id)
                         ->where('transaction_reference', $charge_id)
                         ->first();

        //don't touch if already refunded
        if(!$payment || in_array($payment->status_id, [Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])) {
            return;
        }

        $stripe_driver->client = $payment->client;

        $amount_refunded = $stripe_driver->convertFromStripeAmount($amount_refunded, $payment->client->currency()->precision, $payment->client->currency());

        if ($payment->status_id == Payment::STATUS_PENDING) {
            $payment->service()->deletePayment();
            $payment->status_id = Payment::STATUS_FAILED;
            $payment->save();
            return;
        }

        if($payment->status_id == Payment::STATUS_COMPLETED) {

            $invoice_collection = $payment->paymentables
                        ->where('paymentable_type', 'invoices')
                        ->map(function ($pivot) {
                            return [
                                'invoice_id' => $pivot->paymentable_id,
                                'amount' => $pivot->amount - $pivot->refunded
                            ];
                        });

            if($invoice_collection->count() == 1 && $invoice_collection->first()['amount'] >= $amount_refunded) {
                //If there is only one invoice- and we are refunding _less_ than the amount of the invoice, we can just refund the payment

                $invoice_collection = $payment->paymentables
                        ->where('paymentable_type', 'invoices')
                        ->map(function ($pivot) use ($amount_refunded) {
                            return [
                                'invoice_id' => $pivot->paymentable_id,
                                'amount' => $amount_refunded
                            ];
                        });

            } elseif($invoice_collection->sum('amount') != $amount_refunded) {
                //too many edges cases at this point, return early
                return;
            }

            $invoices = $invoice_collection->toArray();

            $data = [
                'id' => $payment->id,
                'amount' => $amount_refunded,
                'invoices' => $invoices,
                'date' => now()->format('Y-m-d'),
                'gateway_refund' => false,
                'email_receipt' => false,
            ];

            nlog($data);

            $payment->refund($data);

            $payment->private_notes .= 'Refunded via Stripe';
            return;
        }

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company_gateway_id)];
    }
}
