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

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Str;

class AutoBillInvoice extends AbstractService
{
    private $invoice;

    private $client;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;

        $this->client = $invoice->client;
    }

    public function run()
    {
        if (! $this->invoice->isPayable()) {
            return $this->invoice;
        }

        $this->invoice = $this->invoice->service()->markSent()->save();

        if ($this->invoice->balance > 0) {
            $gateway_token = $this->getGateway($this->invoice->balance); //todo what if it is only a partial amount?
        } else {
            return $this->invoice->service()->markPaid()->save();
        }

        if (! $gateway_token || ! $gateway_token->gateway->driver($this->client)->token_billing) {
            return $this->invoice;
        }

        if ($this->invoice->partial > 0) {
            $fee = $gateway_token->gateway->calcGatewayFee($this->invoice->partial);
            // $amount = $this->invoice->partial + $fee;
            $amount = $this->invoice->partial;
        } else {
            $fee = $gateway_token->gateway->calcGatewayFee($this->invoice->balance);
            // $amount = $this->invoice->balance + $fee;
            $amount = $this->invoice->balance;
        }

        $payment_hash = PaymentHash::create([
            'hash' => Str::random(128),
            'data' => ['invoice_id' => $this->invoice->hashed_id, 'amount' => $amount],
            'fee_total' => $fee,
            'fee_invoice_id' => $this->invoice->id,
        ]);

        $payment = $gateway_token->gateway->driver($this->client)->tokenBilling($gateway_token, $payment_hash);

        return $this->invoice;
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
