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

namespace App\Http\ValidationRules\Payment;

use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidRefundableRequest.
 */
class ValidRefundableRequest implements Rule
{
    use MakesHash;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    private $error_msg = '';

    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        if (! array_key_exists('id', $this->input)) {
            $this->error_msg = ctrans('texts.payment_id_required');

            return false;
        }

        $payment = Payment::query()->where('id', $this->input['id'])->withTrashed()->first();

        if (! $payment) {
            $this->error_msg = ctrans('texts.unable_to_retrieve_payment');

            return false;
        }

        $request_invoices = request()->has('invoices') ? $this->input['invoices'] : [];

        if ($payment->invoices()->exists()) {
            $this->checkInvoice($payment->invoices, $request_invoices);
        }

        foreach ($request_invoices as $request_invoice) {
            $this->checkInvoiceIsPaymentable($request_invoice, $payment);
        }

        // Validate total refund doesn't exceed maximum refundable amount
        // Maximum = (payment.amount - payment.refunded) + sum of available credit refunds
        if (! $this->checkTotalRefundableAmount($payment, $request_invoices)) {
            return false;
        }

        if (strlen($this->error_msg) > 1) {
            return false;
        }

        return true;
    }

    private function checkInvoiceIsPaymentable($invoice, $payment)
    {
        /**@var \App\Models\Invoice $invoice **/
        $invoice = Invoice::query()->where('id', $invoice['invoice_id'])->where('company_id', $payment->company_id)->withTrashed()->first();

        if (! $invoice) {
            $this->error_msg = 'Invoice not found for refund';

            return false;
        }

        if ($payment->invoices()->exists()) {
            $paymentable_invoice = $payment->invoices->where('id', $invoice->id)->first();

            if (! $paymentable_invoice) {
                $this->error_msg = ctrans('texts.invoice_not_related_to_payment', ['invoice' => $invoice->number]);

                return false;
            }
        } else {
            $this->error_msg = ctrans('texts.invoice_not_related_to_payment', ['invoice' => $invoice->number]);

            return false;
        }
    }

    private function checkInvoice($paymentables, $request_invoices)
    {
        $record_found = false;

        foreach ($paymentables as $paymentable) {
            foreach ($request_invoices as $request_invoice) {

                if ($request_invoice['invoice_id'] == $paymentable->pivot->paymentable_id) {
                    $record_found = true;

                    $refundable_amount = ($paymentable->pivot->amount - $paymentable->pivot->refunded);

                    if ($request_invoice['amount'] > $refundable_amount) {
                        $invoice = $paymentable;

                        $this->error_msg = ctrans('texts.max_refundable_invoice', ['invoice' => $invoice->hashed_id, 'amount' => $refundable_amount]);

                        return false;
                    }
                }
            }
        }

        if (! $record_found) {
            $this->error_msg = ctrans('texts.refund_without_invoices');

            return false;
        }
    }

    /**
     * Validate that the total refund amount doesn't exceed maximum refundable.
     * Maximum refundable = (payment.amount - payment.refunded) + sum of available credit refunds
     * 
     * This prevents refunding more than what was actually paid (cash + credits).
     *
     * @param Payment $payment
     * @param array $request_invoices
     * @return bool
     */
    private function checkTotalRefundableAmount(Payment $payment, array $request_invoices): bool
    {
        // Calculate total refund amount requested
        $total_refund_requested = 0;
        
        if (count($request_invoices) > 0) {
            $total_refund_requested = collect($request_invoices)->sum('amount');
        } elseif (array_key_exists('amount', $this->input)) {
            $total_refund_requested = $this->input['amount'];
        }

        if ($total_refund_requested <= 0) {
            return true; // No refund requested
        }

        // Calculate maximum refundable from cash portion
        $max_cash_refund = $payment->amount - $payment->refunded;

        // Calculate maximum refundable from credits
        $max_credit_refund = 0;
        if ($payment->credits()->exists()) {
            foreach ($payment->credits as $credit) {
                $max_credit_refund += ($credit->pivot->amount - $credit->pivot->refunded);
            }
        }

        $max_total_refundable = $max_cash_refund + $max_credit_refund;

        if ($total_refund_requested > $max_total_refundable) {
            $this->error_msg = ctrans('texts.max_refundable_payment', [
                'max_refundable' => $max_total_refundable
            ]);

            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->error_msg;
    }
}
