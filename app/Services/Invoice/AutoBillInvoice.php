<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Services\AbstractService;
use App\Utils\Ninja;
use Illuminate\Support\Str;

class AutoBillInvoice extends AbstractService
{
    private $invoice;

    private $client;

    private $used_credit = [];

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;

        $this->client = $invoice->client;
    }

    public function run()
    {
        /* Is the invoice payable? */
        if (! $this->invoice->isPayable()) {
            return $this->invoice;
        }

        /* Mark the invoice as sent */
        $this->invoice = $this->invoice->service()->markSent()->save();

        /* Mark the invoice as paid if there is no balance */
        if ((int)$this->invoice->balance == 0) {
            return $this->invoice->service()->markPaid()->save();
        }

        //if the credits cover the payments, we stop here, build the payment with credits and exit early

        if ($this->client->getSetting('use_credits_payment') != 'off') {
            $this->applyCreditPayment();
        }

        // info("partial = {$this->invoice->partial}");
        // info("balance = {$this->invoice->balance}");

        /* Determine $amount */
        if ($this->invoice->partial > 0) {
            $amount = $this->invoice->partial;
        } elseif ($this->invoice->balance > 0) {
            $amount = $this->invoice->balance;
        } else {
            return $this->invoice;
        }

        info("balance remains to be paid!!");

        $gateway_token = $this->getGateway($amount);

        /* Bail out if no payment methods available */
        if (! $gateway_token || ! $gateway_token->gateway->driver($this->client)->token_billing) {
            return $this->invoice;
        }

        /* $gateway fee */
        $fee = $gateway_token->gateway->calcGatewayFee($amount, $gateway_token->gateway_type_id, $this->invoice->uses_inclusive_taxes);

        //todo determine exact fee as per PaymentController

        /* Build payment hash */
        $payment_hash = PaymentHash::create([
            'hash' => Str::random(128),
            'data' => [['invoice_id' => $this->invoice->hashed_id, 'amount' => $amount]],
            'fee_total' => $fee,
            'fee_invoice_id' => $this->invoice->id,
        ]);

        $payment = $gateway_token->gateway
                                 ->driver($this->client)
                                 ->tokenBilling($gateway_token, $payment_hash);

        return $this->invoice;
    }

    /**
     * If the credits on file cover the invoice amount
     * the we create a matching payment using credits only
     *
     * @return Invoice $invoice
     */
    private function finalizePaymentUsingCredits()
    {
        $amount = array_sum(array_column($this->used_credit, 'amount'));

        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);
        $payment->amount = $amount;
        $payment->applied = $amount;
        $payment->client_id = $this->invoice->client_id;
        $payment->currency_id = $this->invoice->client->getSetting('currency_id');
        $payment->date = now();
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->type_id = PaymentType::CREDIT;
        $payment->service()->applyNumber()->save();

        $payment->invoices()->attach($this->invoice->id, ['amount' => $amount]);

        $this->invoice->service()->setStatus(Invoice::STATUS_PAID)->save();

        foreach ($this->used_credit as $credit) {
            $current_credit = Credit::find($credit['credit_id']);
            $payment->credits()->attach($current_credit->id, ['amount' => $credit['amount']]);

            info("adjusting credit balance {$current_credit->balance} by this amount ". $credit['amount']);

            $current_credit->balance -= $credit['amount'];

            $current_credit->service()->setCalculatedStatus()->save();
            // $this->applyPaymentToCredit($current_credit, $credit['amount']);
        }

        $payment->ledger()
                    ->updatePaymentBalance($amount * -1)
                    ->save();

        $this->invoice->client->service()
                                  ->updateBalance($amount * -1)
                                  ->updatePaidToDate($amount)
                                  ->adjustCreditBalance($amount * -1)
                                  ->save();

        $this->invoice->ledger()
                          ->updateInvoiceBalance($amount * -1, 'Invoice payment using Credit')
                          ->updateCreditBalance($amount * -1, 'Credits used to pay down Invoice ' . $this->invoice->number)
                          ->save();

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        return $this->invoice->service()->setCalculatedStatus()->save();
    }

    /**
     * Applies credits to a payment prior to push
     * to the payment gateway
     *
     * @return $this
     */
    private function applyCreditPayment()
    {
        $available_credits = $this->client
                                  ->credits
                                  ->where('is_deleted', false)
                                  ->where('balance', '>', 0)
                                  ->sortBy('created_at');

        $available_credit_balance = $available_credits->sum('balance');

        info("available credit balance = {$available_credit_balance}");

        if ((int)$available_credit_balance == 0) {
            return;
        }

        $is_partial_amount = false;

        if ($this->invoice->partial > 0) {
            $is_partial_amount = true;
        }

        $this->used_credit = [];

        foreach ($available_credits as $key => $credit) {
            if ($is_partial_amount) {

                //more credit than needed
                if ($credit->balance >= $this->invoice->partial) {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $this->invoice->partial;
                    $this->invoice->balance -= $this->invoice->partial;
                    $this->invoice->partial = 0;
                    break;
                } else {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $credit->balance;
                    $this->invoice->partial -= $credit->balance;
                    $this->invoice->balance -= $credit->balance;
                }
            } else {

                //more credit than needed
                if ($credit->balance >= $this->invoice->balance) {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $this->invoice->balance;
                    $this->invoice->balance = 0;
                    break;
                } else {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $credit->balance;
                    $this->invoice->balance -= $credit->balance;
                }
            }
        }

        $this->finalizePaymentUsingCredits();

        return $this;
    }



    private function applyPaymentToCredit($credit, $amount) :Credit
    {
        $credit_item = new InvoiceItem;
        $credit_item->type_id = '1';
        $credit_item->product_key = ctrans('texts.credit');
        $credit_item->notes = ctrans('texts.credit_payment', ['invoice_number' => $this->invoice->number]);
        $credit_item->quantity = 1;
        $credit_item->cost = $amount * -1;

        $credit_items = $credit->line_items;
        $credit_items[] = $credit_item;

        $credit->line_items = $credit_items;

        $credit = $credit->calc()->getCredit();
        $credit->save();

        return $credit;
    }

    /**
     * Harvests a client gateway token which passes the
     * necessary filters for an $amount.
     *
     * @param  float              $amount The amount to charge
     * @return ClientGatewayToken         The client gateway token
     */
    private function getGateway($amount)
    {
        $gateway_tokens = $this->client->gateway_tokens()->orderBy('is_default', 'DESC')->get();

        foreach ($gateway_tokens as $gateway_token) {
            if ($this->validGatewayLimits($gateway_token, $amount)) {
                return $gateway_token;
            }
        }
    }

    /**
     * Adds a gateway fee to the invoice.
     *
     * @param float $fee The fee amount.
     * @return AutoBillInvoice
     */
    private function addFeeToInvoice(float $fee)
    {

    //todo if we increase the invoice balance here, we will also need to adjust UP the client balance and ledger?
        $starting_amount = $this->invoice->amount;

        $item = new InvoiceItem;
        $item->quantity = 1;
        $item->cost = $fee;
        $item->notes = ctrans('texts.online_payment_surcharge');
        $item->type_id = 3;

        $items = (array) $this->invoice->line_items;
        $items[] = $item;

        $this->invoice->line_items = $items;
        $this->invoice->save();

        $this->invoice = $this->invoice->calc()->getInvoice()->save();

        if ($starting_amount != $this->invoice->amount && $this->invoice->status_id != Invoice::STATUS_DRAFT) {
            $this->invoice->client->service()->updateBalance($this->invoice->amount - $starting_amount)->save();
            $this->invoice->ledger()->updateInvoiceBalance($this->invoice->amount - $starting_amount, 'Invoice balance updated after stale gateway fee removed')->save();
        }

        return $this;
    }

    /**
     * Removes any existing unpaid gateway fees
     * due to previous payment failure.
     *
     * @return $this
     */
    // private function purgeStaleGatewayFees()
    // {
    //     $starting_amount = $this->invoice->amount;

    //     $line_items = $this->invoice->line_items;

    //     $new_items = [];

    //     foreach($line_items as $item)
    //     {

    //       if($item->type_id != 3)
    //         $new_items[] = $item;

    //     }

    //     $this->invoice->line_items = $new_items;
    //     $this->invoice->save();

    //     $this->invoice = $this->invoice->calc()->getInvoice();

    //     if($starting_amount != $this->invoice->amount && $this->invoice->status_id != Invoice::STATUS_DRAFT){
    //         $this->invoice->client->service()->updateBalance($this->invoice->amount - $starting_amount)->save();
    //         $this->invoice->ledger()->updateInvoiceBalance($this->invoice->amount - $starting_amount, 'Invoice balance updated after stale gateway fee removed')->save();
    //     }

    //     return $this;
    // }

    /**
     * Checks whether a given gateway token is able
     * to process the payment after passing through the
     * fees and limits check.
     *
     * @param  CompanyGateway $cg     The CompanyGateway instance
     * @param  float          $amount The amount to be paid
     * @return bool
     */
    public function validGatewayLimits($cg, $amount) : bool
    {
        if (isset($cg->fees_and_limits)) {
            $fees_and_limits = $cg->fees_and_limits->{'1'};
        } else {
            return true;
        }

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !== null && $amount < $fees_and_limits->min_limit) {
            info("amount {$amount} less than ".$fees_and_limits->min_limit);
            $passes = false;
        } elseif ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !== null && $amount > $fees_and_limits->max_limit) {
            info("amount {$amount} greater than ".$fees_and_limits->max_limit);
            $passes = false;
        } else {
            $passes = true;
        }

        return $passes;
    }
}
