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

namespace App\Services\Quickbooks\Invoice;

use App\Models\Invoice;
use App\Models\Paymentable;
use App\Services\Payment\PaymentApplicationDateResolver;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\PaymentTransformer;

class AttachInvoicePayments
{
    public function __construct(private QuickbooksService $service)
    {
    }

    /**
     * @param  array<int, mixed>  $payment_ids
     */
    public function attach(Invoice $invoice, array $payment_ids): void
    {
        $payment_transformer = new PaymentTransformer($this->service->company);

        foreach ($payment_ids as $payment_id) {
            if (!$payment_id) {
                continue;
            }

            $payment = $this->service->sdk()->findById('Payment', $payment_id);

            $ninja_payment = $payment_transformer->buildPayment($payment);
            $ninja_payment->service()->applyNumber()->save();

            $exists = Paymentable::withTrashed()
                ->where('payment_id', $ninja_payment->id)
                ->where('paymentable_id', $invoice->id)
                ->where('paymentable_type', 'invoices')
                ->exists();

            if ($exists) {
                continue;
            }

            $amount = $payment_transformer->appliedAmountForInvoice(
                $payment,
                (string) data_get($invoice->sync, 'qb_id', '')
            );

            if ($amount <= 0) {
                continue;
            }

            $paymentable = new Paymentable();
            $paymentable->payment_id = $ninja_payment->id;
            $paymentable->paymentable_id = $invoice->id;
            $paymentable->paymentable_type = 'invoices';
            $paymentable->amount = $amount;
            $timezone = $this->service->company->timezone()?->name ?: config('app.timezone');
            $paymentable->created_at = app(PaymentApplicationDateResolver::class)
                ->encodeBusinessDate($ninja_payment->date, $timezone);
            $paymentable->save();

            $invoice->service()->applyPayment($ninja_payment, $paymentable->amount);
        }
    }
}
