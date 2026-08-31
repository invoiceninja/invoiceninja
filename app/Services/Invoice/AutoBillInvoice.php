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

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Events\Invoice\InvoiceAutoBillFailed;
use App\Events\Invoice\InvoiceAutoBillSuccess;
use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;
use App\Repositories\CreditRepository;
use App\Repositories\PaymentRepository;
use App\Services\AbstractService;
use App\Services\EDocument\Standards\France\FrancePaymentApplicationRecorder;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Support\Str;

class AutoBillInvoice extends AbstractService
{
    use MakesHash;

    /** The number of failed attempts an invoice is allowed before auto billing is switched off. Shared with AutoBillCron so the selection filter and the cut off cannot drift apart. */
    public const MAX_TRIES = 3;

    private Client $client;

    private array $used_credit = [];

    /*Specific variable for partial payments */
    private bool $is_partial_amount = false;

    public function __construct(private Invoice $invoice, protected string $db)
    {
        $this->client = $this->invoice->client;
    }

    public function run()
    {
        MultiDB::setDb($this->db);

        /* @var \App\Modesl\Client $client */

        /* Mark the invoice as sent */
        $this->invoice = $this->invoice->service()->markSent()->save();

        /* Mark the invoice as paid if there is no balance */
        if (floatval($this->invoice->balance) == 0) {
            return $this->invoice->service()->markPaid()->save();
        }

        /* Is the invoice payable? */
        if (! $this->invoice->refresh()->isPayable()) {
            return $this->invoice;
        }

        //if the credits cover the payments, we stop here, build the payment with credits and exit early
        if ($this->client->getSetting('use_credits_payment') != 'off') {
            $this->applyCreditPayment();
        }

        if ($this->client->getSetting('use_unapplied_payment') != 'off') {
            $this->applyUnappliedPayment();
        }

        //If this returns true, it means a partial invoice amount was paid as a credit and there is no further balance payable
        if (($this->is_partial_amount && $this->invoice->partial == 0) || (int) $this->invoice->balance == 0) {
            return;
        }

        $amount = 0;

        /* Determine $amount */
        if ($this->invoice->partial > 0) {
            $amount = $this->invoice->partial;
        } elseif ($this->invoice->balance > 0) {
            $amount = $this->invoice->balance;
        } else {
            return $this->invoice;
        }

        nlog("Auto Bill - balance remains to be paid!! - {$amount}");
        nlog($this->invoice->amount);
        nlog($this->invoice->balance);

        /**
         * The try is consumed here - before anything can fail - so that a gateway which
         * returns falsely, an error thrown outside the catch below, or a worker that dies
         * mid charge cannot leave the counter untouched and be retried indefinitely.
         */
        $this->invoice->increment('auto_bill_tries', 1);

        /* Retrieve the Client Gateway Token */
        /** @var \App\Models\ClientGatewayToken $gateway_token */
        $gateway_token = $this->getGateway($amount);

        /* Bail out if no payment methods available */
        if (! $gateway_token || ! $gateway_token->gateway || ! $gateway_token->gateway->driver($this->client)->token_billing) {
            nlog('Bailing out - no suitable gateway token found.');

            $this->disableAutoBillIfExhausted();

            throw new \Exception(ctrans('texts.no_payment_method_specified'));
            // return $this->invoice;
        }

        nlog("Gateway present - quoting gateway fee on {$amount}");

        $payment_hash_string = Str::random(32);

        /** The invoice is not touched - the fee reaches it when the payment is confirmed. */
        $quote = $this->invoice->service()->quoteGatewayFee($gateway_token->gateway, $gateway_token->gateway_type_id, $amount);

        $fee = $quote['gross'];
        $fee_net = $quote['net'];

        nlog("fee is {$fee}");

        if ($fee > $amount) {
            $fee = 0;
            $fee_net = 0;
        }

        $fee = round($fee, $this->client->currency()->precision);

        /* Build payment hash */

        $payment_hash = PaymentHash::create([
            'hash' => $payment_hash_string,
            'data' => [
                'amount_with_fee' => round($amount + $fee, $this->client->currency()->precision),
                'fee_net' => $fee_net,
                'invoices' => [
                    [
                        'invoice_id' => $this->invoice->hashed_id,
                        'amount' => $amount,
                        'invoice_number' => $this->invoice->number,
                        'pre_payment' => $this->invoice->is_proforma,
                    ],
                ],
            ],
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

            nlog('payment NOT captured for ' . $this->invoice->number . ' with error ' . $e->getMessage());
            event(new InvoiceAutoBillFailed($this->invoice, $this->invoice->company, Ninja::eventVars(), $e->getMessage()));

            $this->disableAutoBillIfExhausted();

            throw $e;

        }

        if ($payment) {
            nlog('Auto Bill payment captured for ' . $this->invoice->number);

            /** The counter tracks consecutive failures, a capture resets the budget - relevant for partial / instalment invoices which are billed repeatedly. */
            \App\Models\Invoice::where('id', $this->invoice->id)->update(['auto_bill_tries' => 0]);

            event(new InvoiceAutoBillSuccess($this->invoice, $this->invoice->company, Ninja::eventVars()));
        } else {

            /** A driver which reports a decline by returning falsely rather than throwing has still consumed a try. */
            $this->disableAutoBillIfExhausted();
        }
    }

    /**
     * Switches auto billing off once the invoice has exhausted its attempts.
     *
     * The try is consumed before the charge is attempted, so the counter is
     * authoritative by the time a failure lands here. The counter is cleared
     * alongside the flag so that re-enabling auto billing on the invoice grants
     * a fresh budget rather than being silently ignored by AutoBillCron.
     */
    private function disableAutoBillIfExhausted(): void
    {
        $this->invoice->refresh();

        if ($this->invoice->auto_bill_tries < self::MAX_TRIES) {
            return;
        }

        nlog("Auto bill exhausted after {$this->invoice->auto_bill_tries} attempts - disabling auto bill for invoice {$this->invoice->number}");

        \App\Models\Invoice::where('id', $this->invoice->id)->update([
            'auto_bill_enabled' => false,
            'auto_bill_tries' => 0,
        ]);
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

        $payment->amount = 0;
        $payment->applied = 0;
        $payment->client_id = $this->invoice->client_id;
        $payment->currency_id = $this->invoice->client->getSetting('currency_id');
        $payment->date = now()->addSeconds($this->invoice->company->utc_offset())->format('Y-m-d');
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->type_id = PaymentType::CREDIT;
        $payment->service()->applyNumber()->save();

        $payment->invoices()->attach($this->invoice->id, ['amount' => $amount]);

        $this->invoice = $this->invoice
            ->service()
            ->setCalculatedStatus()
            ->save();

        try {
            $this->invoice->loadMissing(['client.country', 'client.company']);

            if ($this->invoice->client->reportableFrTransaction()) {
                $paymentable = Paymentable::withTrashed()
                    ->where('payment_id', $payment->id)
                    ->where('paymentable_id', $this->invoice->id)
                    ->where('paymentable_type', 'invoices')
                    ->latest('id')
                    ->first();

                app(FrancePaymentApplicationRecorder::class)->recordMovement(
                    payment: $payment,
                    invoice: $this->invoice,
                    paymentable: $paymentable,
                    movementAmount: $amount,
                    movementDate: $payment->date ?: now()->toDateString(),
                    movementType: FrancePaymentApplicationRecorder::MOVEMENT_CREDIT_APPLIED,
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        $current_credit = false;

        foreach ($this->used_credit as $credit) {
            $current_credit = Credit::query()->find($credit['credit_id']);
            $payment->credits()
                    ->attach($current_credit->id, ['amount' => $credit['amount']]);

            nlog("adjusting credit balance {$current_credit->balance} by this amount " . $credit['amount']);


            $item_date = Carbon::parse($payment->date)->format($payment->client->date_format());
            $invoice_numbers = $this->invoice->number;

            $item = new InvoiceItem();
            $item->quantity = 0;
            $item->cost = $credit['amount'] * -1;
            $item->notes = "{$item_date} - " . ctrans('texts.credit_payment', ['invoice_number' => $invoice_numbers]) . " " . Number::formatMoney($credit['amount'], $payment->client);
            $item->type_id = "1";

            $line_items = $current_credit->line_items;
            $line_items[] = $item;
            $current_credit->line_items = $line_items;


            $current_credit->service()
                           ->adjustBalance($credit['amount'] * -1)
                           ->updatePaidToDate($credit['amount'])
                           ->setCalculatedStatus()
                           ->save();

        }

        $payment->ledger()
            ->updatePaymentBalance($amount * -1, "AutoBill")
            ->save();

        $this->invoice
             ->client
             ->service()
             ->updateBalanceAndPaidToDate($amount * -1, $amount)
             ->adjustCreditBalance($amount * -1)
             ->save();

        $credit_reference = $current_credit ? $current_credit->number : 'unknown';

        $this->invoice->ledger() //09-03-2022
                      ->updateCreditBalance($amount * -1, "Credit {$credit_reference} used to pay down Invoice {$this->invoice->number}")
                      ->save();

        event('eloquent.created: App\Models\Payment', $payment);
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        //if we have paid the invoice in full using credits, then we need to fire the event
        if ($this->invoice->balance == 0) {
            event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars()));
        }

        return $this->invoice
                    ->service()
                    ->setCalculatedStatus()
                    ->workFlow() //07-06-2024 - run the workflow if paid!
                    ->save();
    }

    /**
     * If the client has unapplied payments on file
     * we will use these prior to charging a
     * payment method on file.
     *
     * This needs to be wrapped in a transaction.
     *
     * @return self
     */
    public function applyUnappliedPayment(): self
    {
        $unapplied_payments = Payment::query()
                                  ->where('client_id', $this->client->id)
                                  ->where('status_id', Payment::STATUS_COMPLETED)
                                  ->where('is_deleted', false)
                                  ->whereColumn('amount', '>', 'applied')
                                  ->where('amount', '>', 0)
                                  ->orderBy('created_at')
                                  ->get();

        $available_unapplied_balance = $unapplied_payments->sum('amount') - $unapplied_payments->sum('applied');

        nlog($this->client->id);
        nlog($this->invoice->id);
        nlog($unapplied_payments->sum('amount'));
        nlog($unapplied_payments->sum('applied'));

        nlog("available unapplied balance = {$available_unapplied_balance}");

        if ((int) $available_unapplied_balance == 0) {
            return $this;
        }

        if ($this->invoice->partial > 0) {
            $this->is_partial_amount = true;
        }

        $payment_repo = new PaymentRepository(new CreditRepository());

        foreach ($unapplied_payments as $key => $payment) {
            $payment_balance = $payment->amount - $payment->applied;

            if ($this->is_partial_amount) {
                //more than needed
                if ($payment_balance > $this->invoice->partial) {
                    $payload = ['client_id' => $this->invoice->client_id, 'invoices' => [['invoice_id' => $this->invoice->id,'amount' => $this->invoice->partial]]];
                    $payment_repo->save($payload, $payment);

                    $this->invoice = $this->invoice->fresh();

                    return $this;
                } else {
                    $payload = ['client_id' => $this->invoice->client_id, 'invoices' => [['invoice_id' => $this->invoice->id,'amount' => $payment_balance]]];
                    $payment_repo->save($payload, $payment);
                }
            } else {
                //more than needed
                if ($payment_balance > $this->invoice->balance) {

                    $payload = ['client_id' => $this->invoice->client_id, 'invoices' => [['invoice_id' => $this->invoice->id,'amount' => $this->invoice->balance]]];
                    $payment_repo->save($payload, $payment);

                    $this->invoice = $this->invoice->fresh();

                    return $this;

                } else {

                    $payload = ['client_id' => $this->invoice->client_id, 'invoices' => [['invoice_id' => $this->invoice->id,'amount' => $payment_balance]]];
                    $payment_repo->save($payload, $payment);

                    $this->invoice = $this->invoice->fresh();

                }
            }

            if ((int) $this->invoice->balance == 0) {
                event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars()));
                return $this;
            }
        }

        return $this;
    }

    /**
     * Applies credits to a payment prior to push
     * to the payment gateway
     *
     * @return self
     */
    public function applyCreditPayment(): self
    {
        $available_credits = Credit::query()->where('client_id', $this->client->id)
                                  ->where('is_deleted', false)
                                  ->where('balance', '>', 0)
                                  ->orderBy('created_at')
                                  ->get();

        $available_credit_balance = $available_credits->sum('balance');

        nlog("available credit balance = {$available_credit_balance}");

        if ((int) $available_credit_balance == 0) {
            return $this;
        }

        if ($this->invoice->partial > 0) {
            $this->is_partial_amount = true;
        }

        $this->used_credit = [];

        foreach ($available_credits as $key => $credit) {
            if ($this->is_partial_amount) {
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


    /**
     * Harvests a client gateway token which passes the
     * necessary filters for an $amount.
     *
     * @param  float              $amount The amount to charge
     * @return ClientGatewayToken | bool        The client gateway token
     */
    public function getGateway($amount)
    {
        $company_gateway_ids = $this->client->getSetting('company_gateway_ids');

        $transformed_ids = false;

        //gateways are disabled!
        if ($company_gateway_ids == "0") {
            return false;
        } elseif (strlen($company_gateway_ids ?? '')  > 2) {

            // If the client has a special gateway configuration, we need to ensure we only use the ones that are enabled!
            $transformed_ids = $this->transformKeys(explode(',', $company_gateway_ids));
        }

        //get all client gateway tokens and set the is_default one to the first record
        $gateway_tokens = \App\Models\ClientGatewayToken::query()
                                ->where('client_id', $this->client->id)
                                ->where('is_deleted', 0)
                                ->whereHas('gateway', function ($query) use ($transformed_ids) {
                                    $query->where('is_deleted', 0)
                                            ->where('deleted_at', null)
                                            ->when($transformed_ids, function ($q) use ($transformed_ids) {
                                                $q->whereIn('id', $transformed_ids);
                                            });
                                })
                                ->orderBy('is_default', 'DESC')
                                ->get();

        $filtered_gateways = $gateway_tokens->filter(function (ClientGatewayToken $gateway_token) use ($amount): bool {
            $company_gateway = $gateway_token->gateway;

            if (! $this->canAutoBill($gateway_token)) {
                return false;
            }

            //check if fees and limits are set
            if (isset($company_gateway->fees_and_limits) && ! is_array($company_gateway->fees_and_limits) && property_exists($company_gateway->fees_and_limits, $gateway_token->gateway_type_id)) { //@phpstan-ignore-line
                return $this->invoice->client->validGatewayForAmount($company_gateway->fees_and_limits->{$gateway_token->gateway_type_id}, $amount);
            }

            return true; //if no fees_and_limits set then we automatically must add this gateway
        });

        if ($filtered_gateways->count() >= 1) {
            return $filtered_gateways->first();
        }

        return false;
    }

    private function canAutoBill(ClientGatewayToken $gateway_token): bool
    {
        if ($gateway_token->gateway_type_id !== GatewayType::BANK_TRANSFER) {
            return true;
        }

        $driver = $gateway_token->gateway?->driver($this->client);

        if (! $driver instanceof StripePaymentDriver) {
            return true;
        }

        return ($gateway_token->meta->state ?? null) === 'authorized';
    }

}
