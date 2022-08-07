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

use App\DataMapper\InvoiceItem;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Libraries\MultiDB;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Services\AbstractService;
use App\Utils\Ninja;
use Illuminate\Support\Str;
use PDO;

class AutoBillInvoice extends AbstractService
{
    private $invoice;

    private $client;

    private $used_credit = [];

    protected $db;

    public function __construct(Invoice $invoice, $db)
    {
        $this->invoice = $invoice;

        $this->db = $db;
    }

    public function run()
    {
        MultiDB::setDb($this->db);

        $this->client = $this->invoice->client->fresh();

        $is_partial = false;

        /* Is the invoice payable? */
        if (! $this->invoice->isPayable()) {
            return $this->invoice;
        }

        /* Mark the invoice as sent */
        $this->invoice = $this->invoice->service()->markSent()->save();

        /* Mark the invoice as paid if there is no balance */
        if ((int) $this->invoice->balance == 0) {
            return $this->invoice->service()->markPaid()->save();
        }

        //if the credits cover the payments, we stop here, build the payment with credits and exit early
        if ($this->client->getSetting('use_credits_payment') != 'off') {
            $this->applyCreditPayment();
        }

        $amount = 0;

        /* Determine $amount */
        if ($this->invoice->partial > 0) {
            $is_partial = true;
            $invoice_total = $this->invoice->balance;
            $amount = $this->invoice->partial;
        } elseif ($this->invoice->balance > 0) {
            $amount = $this->invoice->balance;
        } else {
            return $this->invoice;
        }

        info("Auto Bill - balance remains to be paid!! - {$amount}");

        /* Retrieve the Client Gateway Token */
        $gateway_token = $this->getGateway($amount);

        /* Bail out if no payment methods available */
        if (! $gateway_token || ! $gateway_token->gateway || ! $gateway_token->gateway->driver($this->client)->token_billing) {
            nlog('Bailing out - no suitable gateway token found.');

            return $this->invoice;
        }

        nlog('Gateway present - adding gateway fee');

        /* $gateway fee */
        $this->invoice = $this->invoice->service()->addGatewayFee($gateway_token->gateway, $gateway_token->gateway_type_id, $amount)->save();

        //change from $this->invoice->amount to $this->invoice->balance
        if ($is_partial) {
            $fee = $this->invoice->balance - $invoice_total;
        } else {
            $fee = $this->invoice->balance - $amount;
        }

        if ($fee > $amount) {
            $fee = 0;
        }

        /* Build payment hash */

        $payment_hash = PaymentHash::create([
            'hash' => Str::random(64),
            'data' => ['invoices' => [['invoice_id' => $this->invoice->hashed_id, 'amount' => $amount, 'invoice_number' => $this->invoice->number]]],
            'fee_total' => $fee,
            'fee_invoice_id' => $this->invoice->id,
        ]);

        nlog("Payment hash created => {$payment_hash->id}");

        $payment = false;

        try {
            $payment = $gateway_token->gateway
                ->driver($this->client)
                ->setPaymentHash($payment_hash)
                ->tokenBilling($gateway_token, $payment_hash);
        } catch (\Exception $e) {
            $this->invoice->auto_bill_tries += 1;

            if ($this->invoice->auto_bill_tries == 3) {
                $this->invoice->auto_bill_enabled = false;
                $this->invoice->auto_bill_tries = 0; //reset the counter here in case auto billing is turned on again in the future.
                $this->invoice->save();
            }

            $this->invoice->save();

            nlog('payment NOT captured for '.$this->invoice->number.' with error '.$e->getMessage());
        }

        if ($payment) {
            info('Auto Bill payment captured for '.$this->invoice->number);
        }
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

        $this->invoice
             ->service()
             ->setStatus(Invoice::STATUS_PAID)
             ->save();

        foreach ($this->used_credit as $credit) {
            $current_credit = Credit::find($credit['credit_id']);
            $payment->credits()
                    ->attach($current_credit->id, ['amount' => $credit['amount']]);

            info("adjusting credit balance {$current_credit->balance} by this amount ".$credit['amount']);

            $current_credit->service()
                           ->adjustBalance($credit['amount'] * -1)
                           ->updatePaidToDate($credit['amount'])
                           ->setCalculatedStatus()
                           ->save();
        }

        $payment->ledger()
            ->updatePaymentBalance($amount * -1)
            ->save();

        $client = $this->invoice->client->fresh();

        $client->service()
              ->updateBalance($amount * -1)
              ->updatePaidToDate($amount)
              ->adjustCreditBalance($amount * -1)
              ->save();

        $this->invoice->ledger() //09-03-2022
                          // ->updateInvoiceBalance($amount * -1, "Invoice {$this->invoice->number} payment using Credit {$current_credit->number}")
                          ->updateCreditBalance($amount * -1, "Credit {$current_credit->number} used to pay down Invoice {$this->invoice->number}")
                          ->save();

        event('eloquent.created: App\Models\Payment', $payment);
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        return $this->invoice
                    ->service()
                    ->setCalculatedStatus()
                    ->save();
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

        if ((int) $available_credit_balance == 0) {
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
                if ($credit->balance > $this->invoice->partial) {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $this->invoice->partial;
                    $this->invoice->balance -= $this->invoice->partial;
                    $this->invoice->paid_to_date += $this->invoice->partial;
                    $this->invoice->partial = 0;
                    break;
                } else {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $credit->balance;
                    $this->invoice->partial -= $credit->balance;
                    $this->invoice->balance -= $credit->balance;
                    $this->invoice->paid_to_date += $credit->balance;
                }
            } else {

                //more credit than needed
                if ($credit->balance > $this->invoice->balance) {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $this->invoice->balance;
                    $this->invoice->paid_to_date += $this->invoice->balance;
                    $this->invoice->balance = 0;

                    break;
                } else {
                    $this->used_credit[$key]['credit_id'] = $credit->id;
                    $this->used_credit[$key]['amount'] = $credit->balance;
                    $this->invoice->balance -= $credit->balance;
                    $this->invoice->paid_to_date += $credit->balance;
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
    public function getGateway($amount)
    {

        //get all client gateway tokens and set the is_default one to the first record
        $gateway_tokens = $this->client
                               ->gateway_tokens()
                               ->whereHas('gateway', function ($query) {
                                   $query->where('is_deleted', 0)
                                          ->where('deleted_at', null);
                               })->orderBy('is_default', 'DESC')
                               ->get();

        $filtered_gateways = $gateway_tokens->filter(function ($gateway_token) use ($amount) {
            $company_gateway = $gateway_token->gateway;

            //check if fees and limits are set
            if (isset($company_gateway->fees_and_limits) && ! is_array($company_gateway->fees_and_limits) && property_exists($company_gateway->fees_and_limits, $gateway_token->gateway_type_id)) {
                //if valid we keep this gateway_token
                if ($this->invoice->client->validGatewayForAmount($company_gateway->fees_and_limits->{$gateway_token->gateway_type_id}, $amount)) {
                    return true;
                } else {
                    return false;
                }
            }

            return true; //if no fees_and_limits set then we automatically must add this gateway
        });

        if ($filtered_gateways->count() >= 1) {
            return $filtered_gateways->first();
        }

        return false;
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
        $this->invoice->saveQuietly();

        $this->invoice = $this->invoice->calc()->getInvoice()->saveQuietly();

        if ($starting_amount != $this->invoice->amount && $this->invoice->status_id != Invoice::STATUS_DRAFT) {
            $this->invoice->client->service()->updateBalance($this->invoice->amount - $starting_amount)->save();
            $this->invoice->ledger()->updateInvoiceBalance($this->invoice->amount - $starting_amount, "Invoice {$this->invoice->number} balance updated after stale gateway fee removed")->save();
        }

        return $this;
    }
}
