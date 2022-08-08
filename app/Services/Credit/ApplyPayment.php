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

namespace App\Services\Credit;

use App\DataMapper\InvoiceItem;
use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Ninja;

class ApplyPayment
{
    private $credit;

    private $invoice;

    private $amount;

    private $amount_applied;

    private $payment;

    public function __construct(Credit $credit, Invoice $invoice, float $amount, Payment $payment)
    {
        $this->credit = $credit;
        $this->invoice = $invoice;
        $this->amount = $amount;
        $this->amount_applied = 0;
        $this->payment = $payment->fresh();
    }

    public function run() :Credit
    {

        //$available_credit_balance = $this->credit->balance;
        $applicable_amount = min($this->amount, $this->credit->balance);
        $invoice_balance = $this->invoice->balance;
        $credit_balance = $this->credit->balance;

        /* Check invoice partial for amount to be cleared first */
        if ($this->invoice->partial > 0) {
            $partial_payment = min($this->invoice->partial, $applicable_amount);

            $this->invoice->partial -= $partial_payment;
            $invoice_balance -= $partial_payment;
            $this->amount -= $partial_payment;
            $credit_balance -= $partial_payment;
            $applicable_amount -= $partial_payment;
            $this->amount_applied += $partial_payment;
        }

        /* If there is remaining amount use it on the balance */
        if ($this->amount > 0 && $applicable_amount > 0 && $invoice_balance > 0) {
            $balance_payment = min($invoice_balance, min($this->amount, $credit_balance));

            $invoice_balance -= $balance_payment;
            $this->amount -= $balance_payment;
            $this->amount_applied += $balance_payment;
        }

        $this->credit->balance -= $this->amount_applied;
        $this->credit->paid_to_date += $this->amount_applied;

        if ((int) $this->credit->balance == 0) {
            $this->credit->status_id = Credit::STATUS_APPLIED;
        } else {
            $this->credit->status_id = Credit::STATUS_PARTIAL;
        }

        $this->credit->save();

        $this->addPaymentToLedger();

        return $this->credit;
    }

    private function applyPaymentToCredit()
    {
        $credit_item = new InvoiceItem;
        $credit_item->type_id = '1';
        $credit_item->product_key = ctrans('texts.credit');
        $credit_item->notes = ctrans('texts.credit_payment', ['invoice_number' => $this->invoice->number]);
        $credit_item->quantity = 1;
        $credit_item->cost = $this->amount_applied * -1;

        $credit_items = $this->credit->line_items;
        $credit_items[] = $credit_item;

        $this->credit->line_items = $credit_items;

        $this->credit = $this->credit->calc()->getCredit();
        $this->credit->save();
    }

    private function addPaymentToLedger()
    {
        $this->payment->amount += $this->amount_applied;
        $this->payment->applied += $this->amount_applied;
        $this->payment->status_id = Payment::STATUS_COMPLETED;
        $this->payment->currency_id = $this->credit->client->getSetting('currency_id');
        $this->payment->save();

        $this->payment->service()->applyNumber()->save();

        $this->payment
             ->invoices()
             ->attach($this->invoice->id, ['amount' => $this->amount_applied]);

        $this->payment
             ->credits()
             ->attach($this->credit->id, ['amount' => $this->amount_applied]);

        $this->payment
                 ->ledger()
                 ->updatePaymentBalance($this->amount_applied * -1);

        $this->payment
                 ->client
                 ->service()
                 ->updateBalance($this->amount_applied * -1)
                 ->adjustCreditBalance($this->amount_applied * -1)
                 ->updatePaidToDate($this->amount_applied)
                 ->save();

        $this->invoice
                 ->service()
                 ->updateBalance($this->amount_applied * -1)
                 ->updatePaidToDate($this->amount_applied)
                 ->updateStatus()
                 ->save();

        $this->credit
                 ->ledger()
                 ->updateCreditBalance(($this->amount_applied * -1), "Credit payment applied to Invoice {$this->invoice->number}");

        event(new InvoiceWasUpdated($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        if ((int) $this->invoice->balance == 0) {
            $this->invoice->service()->deletePdf();
            $this->invoice = $this->invoice->fresh();
            event(new InvoiceWasPaid($this->invoice, $this->payment, $this->payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }
    }
}
