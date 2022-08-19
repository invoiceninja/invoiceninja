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

namespace App\Services\Invoice;

use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Facades\DB;

class HandleRestore extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    private $payment_total = 0;

    private $total_payments = 0;

    private $adjustment_amount = 0;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        $this->invoice->restore();

        if (! $this->invoice->is_deleted) {
            return $this->invoice;
        }

        //determine whether we need to un-delete payments OR just modify the payment amount /applied balances.

        foreach ($this->invoice->payments as $payment) {
            //restore the payment record
            $this->invoice->restore();
        }

        //adjust ledger balance
        $this->invoice->ledger()->updateInvoiceBalance($this->invoice->balance, "Restored invoice {$this->invoice->number}")->save();

        $this->invoice->client
                      ->service()
                      ->updateBalance($this->invoice->balance)
                      ->updatePaidToDate($this->invoice->paid_to_date)
                      ->save();

        $this->windBackInvoiceNumber();

        $this->invoice->is_deleted = false;
        $this->invoice->save();

        $this->restorePaymentables()
             ->setAdjustmentAmount()
             ->adjustPayments();

        return $this->invoice;
    }

    /* Touches all paymentables as deleted */
    private function restorePaymentables()
    {
        $this->invoice->payments->each(function ($payment) {
            $payment->paymentables()
                    ->where('paymentable_type', '=', 'invoices')
                    ->where('paymentable_id', $this->invoice->id)
                    ->update(['deleted_at' => false]);
        });

        return $this;
    }


    private function setAdjustmentAmount()
    {
        foreach ($this->invoice->payments as $payment) {
            $this->adjustment_amount += $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum(DB::raw('amount'));

            $this->adjustment_amount += $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum(DB::raw('refunded'));
        }

        $this->total_payments = $this->invoice->payments->sum('amount') - $this->invoice->payments->sum('refunded');

        return $this;
    }    

    private function adjustPayments()
    {
        //if total payments = adjustment amount - that means we need to delete the payments as well.

        if ($this->adjustment_amount == $this->total_payments) {
            $this->invoice->payments()->update(['payments.deleted_at' => null, 'payments.is_deleted' => false]);
        } else {

            //adjust payments down by the amount applied to the invoice payment.

            $this->invoice->payments->each(function ($payment) {
                $payment_adjustment = $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum(DB::raw('amount'));

                $payment_adjustment -= $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum(DB::raw('refunded'));

                $payment->amount += $payment_adjustment;
                $payment->applied += $payment_adjustment;
                $payment->is_deleted = false;
                $payment->restore();
                $payment->save();
            });
        }

        return $this;
    }


    private function windBackInvoiceNumber()
    {
        $findme = '_'.ctrans('texts.deleted');

        $pos = strpos($this->invoice->number, $findme);

        $new_invoice_number = substr($this->invoice->number, 0, $pos);

        if (strlen($new_invoice_number) == 0) {
            $new_invoice_number = null;
        }

        try {
            $exists = Invoice::where(['company_id' => $this->invoice->company_id, 'number' => $new_invoice_number])->exists();

            if ($exists) {
                $this->invoice->number = $this->getNextInvoiceNumber($this->invoice->client, $this->invoice, $this->invoice->recurring_id);
            } else {
                $this->invoice->number = $new_invoice_number;
            }

            $this->invoice->saveQuietly();
        } catch (\Exception $e) {
            nlog('I could not wind back the invoice number');

            if (Ninja::isHosted()) {
                \Sentry\captureMessage('I could not wind back the invoice number');
                app('sentry')->captureException($e);
            }
        }
    }
}
