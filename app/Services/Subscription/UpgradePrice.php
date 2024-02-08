<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Subscription;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class UpgradePrice extends AbstractService
{    
    protected \App\Services\Subscription\SubscriptionStatus $status;

    public function __construct(protected RecurringInvoice $recurring_invoice, public Subscription $subscription)
    {
    }

    public function run(): float
    {

        $this->status = $this->recurring_invoice
                       ->subscription
                       ->status($this->recurring_invoice);

        if($this->status->is_trial || !$this->status->is_in_good_standing)
            return $this->subscription->price; 

        if($this->status->is_in_good_standing)
            return $this->calculateUpgrade();
        
    }

    private function calculateUpgrade(): float
    {
        $ratio = $this->status->getProRataRatio();

        $last_invoice = $this->recurring_invoice
                             ->invoices()
                             ->where('is_deleted', 0)
                             ->where('is_proforma', 0)
                             ->orderBy('id', 'desc')
                             ->first();
        
        $refund = $this->getRefundableAmount($last_invoice, $ratio);
        $outstanding_credit = $this->getCredits();
        
        nlog("{$this->subscription->price} - {$refund} - {$outstanding_credit}");

        return $this->subscription->price - $refund - $outstanding_credit;

    }

    private function getRefundableAmount(?Invoice $invoice, float $ratio): float
    {
        if (!$invoice || !$invoice->date || $invoice->status_id != Invoice::STATUS_PAID || $ratio == 0) 
            return 0;

        return max(0, round(($invoice->paid_to_date*$ratio),2));
    }

    private function getCredits(): float
    {
        $outstanding_credits = 0;

        $use_credit_setting = $this->recurring_invoice->client->getSetting('use_credits_payment');

        if($use_credit_setting){
            
            $outstanding_credits = Credit::query()
                               ->where('client_id', $this->recurring_invoice->client_id)
                               ->whereIn('status_id', [Credit::STATUS_SENT,Credit::STATUS_PARTIAL])
                               ->where('is_deleted', 0)
                               ->where('balance', '>', 0)
                               ->sum('balance');

        }

        return $outstanding_credits;
    }
    
}