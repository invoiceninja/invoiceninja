<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Credit;

use App\DataMapper\InvoiceItem;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;

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

    public function run() :Invoice
    {

        //$available_credit_balance = $this->credit->balance;
        $applicable_amount = min($this->amount, $this->credit->balance);
        //check invoice partial for amount to be cleared first
        
        if($this->invoice->partial > 0){

            $partial_payment = min($this->invoice->partial, $applicable_amount);

            $this->invoice->partial -= $partial_payment;
            $this->invoice->balance -= $partial_payment;
            $this->amount -= $partial_payment;
            // $this->credit->balance -= $partial_payment;
            $applicable_amount -= $partial_payment;
            $this->amount_applied += $partial_payment;

        }

        if($this->amount > 0 && $applicable_amount > 0 && $this->invoice->balance > 0){

            $balance_payment = min($this->invoice->balance, $this->amount);

            $this->invoice->balance -= $balance_payment;
            $this->amount -= $balance_payment;
            // $this->credit->balance -= $balance_payment;
            $this->amount_applied += $balance_payment;

        }

        return $this->invoice;

    }

    private function applyPaymentToCredit()
    {

        $credit_item = new InvoiceItem;
        $credit_item->type_id = '1';
        $credit_item->product_key = ctrans('texts.credit');
        $credit_item->notes = ctrans('texts.credit_payment', ['invoice_number' => $this->invoice->number]);
        $credit_item->quantity = 1;
        $credit_item->cost = $this->amount_applied * -1;

        $credit_items = $credit->line_items;
        $credit_items[] = $credit_item;

        $this->credit->line_items = $credit_items;

        $this->credit = $this->credit->calc()->getCredit();
        $this->credit->save();

    }


}
