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

namespace App\Helpers\Subscription;

use App\Helpers\Invoice\ProRata;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * SubscriptionCalculator.
 */
class SubscriptionCalculator
{
    public Subscription $target_subscription;

    public Invoice $invoice;

    public function __construct(Subscription $target_subscription, Invoice $invoice)
    {
        $this->target_subscription = $target_subscription;
        $this->invoice = $invoice;
    }

    /**
     * Tests if the user is currently up
     * to date with their payments for
     * a given recurring invoice
     *
     * @return bool
     */
    public function isPaidUp() :bool
    {
        $outstanding_invoices_exist = Invoice::whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                             ->where('subscription_id', $this->invoice->subscription_id)
                                             ->where('client_id', $this->invoice->client_id)
                                             ->where('balance', '>', 0)
                                             ->exists();

        return ! $outstanding_invoices_exist;
    }

    public function calcUpgradePlan()
    {
        //set the starting refund amount
        $refund_amount = 0;

        $refund_invoice = false;

        //are they paid up to date.

        //yes - calculate refund
        if ($this->isPaidUp()) {
            $refund_invoice = $this->getRefundInvoice();
        }

        if ($refund_invoice) {
            $subscription = Subscription::find($this->invoice->subscription_id);
            $pro_rata = new ProRata;

            $to_date = $subscription->service()->getNextDateForFrequency(Carbon::parse($refund_invoice->date), $subscription->frequency_id);

            $refund_amount = $pro_rata->refund($refund_invoice->amount, now(), $to_date, $subscription->frequency_id);

            $charge_amount = $pro_rata->charge($this->target_subscription->price, now(), $to_date, $this->target_subscription->frequency_id);

            return $charge_amount - $refund_amount;
        }

        //no - return full freight charge.
        return $this->target_subscription->price;
    }

    public function executeUpgradePlan()
    {
    }

    private function getRefundInvoice()
    {
        return Invoice::where('subscription_id', $this->invoice->subscription_id)
                      ->where('client_id', $this->invoice->client_id)
                      ->where('is_deleted', 0)
                      ->orderBy('id', 'desc')
                      ->first();
    }
}
