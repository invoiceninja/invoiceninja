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

use App\Jobs\Ninja\TransactionLog;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Facades\DB;

class MarkInvoiceDeleted extends AbstractService
{
    use GeneratesCounter;

    private $invoice;

    private $adjustment_amount = 0;

    private $total_payments = 0;

    private $balance_adjustment = 0;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function run()
    {
        if ($this->invoice->is_deleted) {
            return $this->invoice;
        }

        $this->cleanup()
             ->setAdjustmentAmount()
             ->deletePaymentables()
             ->adjustPayments()
             ->adjustPaidToDate()
             ->adjustBalance()
             ->adjustLedger();

        $transaction = [
            'invoice' => $this->invoice->transaction_event(),
            'payment' => $this->invoice->payments()->exists() ? $this->invoice->payments()->first()->transaction_event() : [],
            'client' => $this->invoice->client->transaction_event(),
            'credit' => [],
            'metadata' => ['total_payments' => $this->total_payments, 'balance_adjustment' => $this->balance_adjustment, 'adjustment_amount' => $this->adjustment_amount],
        ];

        TransactionLog::dispatch(TransactionEvent::INVOICE_DELETED, $transaction, $this->invoice->company->db);

        return $this->invoice;
    }

    private function adjustLedger()
    {
        // $this->invoice->ledger()->updatePaymentBalance($this->adjustment_amount * -1, 'Invoice Deleted - reducing ledger balance'); //reduces the payment balance by payment totals
        $this->invoice->ledger()->updatePaymentBalance($this->balance_adjustment * -1, 'Invoice Deleted - reducing ledger balance'); //reduces the payment balance by payment totals

        return $this;
    }

    private function adjustPaidToDate()
    {
        $client = $this->invoice->client->fresh();
        $client->paid_to_date += $this->adjustment_amount * -1;
        $client->save();
        // $this->invoice->client->service()->updatePaidToDate($this->adjustment_amount * -1)->save(); //reduces the paid to date by the payment totals

        return $this;
    }

    private function adjustBalance()
    {
        $client = $this->invoice->client->fresh();
        $client->balance += $this->balance_adjustment * -1;
        $client->save();

        // $this->invoice->client->service()->updateBalance($this->balance_adjustment * -1)->save(); //reduces the client balance by the invoice amount.

        return $this;
    }

    /* Adjust the payment amounts */
    private function adjustPayments()
    {
        //if total payments = adjustment amount - that means we need to delete the payments as well.

        if ($this->adjustment_amount == $this->total_payments) {
            $this->invoice->payments()->update(['payments.deleted_at' => now(), 'payments.is_deleted' => true]);
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

                $payment->amount -= $payment_adjustment;
                $payment->applied -= $payment_adjustment;
                $payment->save();
            });
        }

        return $this;
    }

    /**
     * Set the values of two variables
     *
     * $this->adjustment_amount - sum of the invoice paymentables
     * $this->total_payments - sum of the invoice payments
     */
    private function setAdjustmentAmount()
    {
        foreach ($this->invoice->payments as $payment) {
            $this->adjustment_amount += $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum(DB::raw('amount'));

            $this->adjustment_amount -= $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum(DB::raw('refunded'));
        }

        $this->total_payments = $this->invoice->payments->sum('amount') - $this->invoice->payments->sum('refunded');

        $this->balance_adjustment = $this->invoice->balance;

        return $this;
    }

    /*
     *
     * This sets the invoice number to _deleted
     * and also removes the links to existing entities
     *
     */
    private function cleanup()
    {
        $check = false;

        $x = 0;

        do {
            $number = $this->calcNumber($x);
            $check = $this->checkNumberAvailable(Invoice::class, $this->invoice, $number);
            $x++;
        } while (! $check);

        $this->invoice->number = $number;

        //wipe references to invoices from related entities.
        $this->invoice->tasks()->update(['invoice_id' => null]);
        $this->invoice->expenses()->update(['invoice_id' => null]);

        return $this;
    }

    private function calcNumber($x)
    {
        if ($x == 0) {
            $number = $this->invoice->number.'_'.ctrans('texts.deleted');
        } else {
            $number = $this->invoice->number.'_'.ctrans('texts.deleted').'_'.$x;
        }

        return $number;
    }

    /* Touches all paymentables as deleted */
    private function deletePaymentables()
    {
        $this->invoice->payments->each(function ($payment) {
            $payment->paymentables()
                    ->where('paymentable_type', '=', 'invoices')
                    ->where('paymentable_id', $this->invoice->id)
                    ->update(['deleted_at' => now()]);
        });

        return $this;
    }
}
