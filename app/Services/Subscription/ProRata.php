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

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class ProRata extends AbstractService
{    
    /** @var bool $is_trial */
    private bool $is_trial = false;
        
    /** @var \Illuminate\Database\Eloquent\Collection<Invoice> | null $unpaid_invoices */
    private $unpaid_invoices = null;
    
    /** @var bool $refundable */
    private bool $refundable = false;
    
    /** @var int $pro_rata_duration */
    private int $pro_rata_duration = 0;
        
    /** @var int $subscription_interval_duration */
    private int $subscription_interval_duration = 0;
    
    /** @var int $pro_rata_ratio */
    private int $pro_rata_ratio = 1;

    public function __construct(public Subscription $subscription, protected RecurringInvoice $recurring_invoice)
    {
    }

    public function run()
    {
        $this->setCalculations();
    }

    private function setCalculations(): self
    {
        $this->isInTrialPeriod()
             ->checkUnpaidInvoices()
             ->checkRefundPeriod()
             ->checkProRataDuration()
             ->calculateSubscriptionIntervalDuration()
             ->calculateProRataRatio();

        return $this;
    }
                
    /**
     * Calculates the number of seconds
     * of the current interval that has been used.
     *
     * @return self
     */
    private function checkProRataDuration(): self
    {
        
        $primary_invoice = $this->recurring_invoice
                                ->invoices()
                                ->where('is_deleted', 0)
                                ->where('is_proforma', 0)
                                ->orderBy('id', 'desc')
                                ->first();

        $duration = Carbon::parse($primary_invoice->date)->startOfDay()->diffInSeconds(now());

        $this->setProRataDuration(max(0, $duration));

        return $this;
    }

    private function calculateProRataRatio(): self
    {
        if($this->pro_rata_duration < $this->subscription_interval_duration)
            $this->setProRataRatio($this->pro_rata_duration/$this->subscription_interval_duration);

        return $this;
    }


    private function calculateSubscriptionIntervalDuration(): self
    {
        if($this->getIsTrial())
            return $this->setSubscriptionIntervalDuration(0);

        $primary_invoice = $this->recurring_invoice
                                ->invoices()
                                ->where('is_deleted', 0)
                                ->where('is_proforma', 0)
                                ->orderBy('id', 'desc')
                                ->first();

        if(!$primary_invoice)
            return $this->setSubscriptionIntervalDuration(0);

        $start = Carbon::parse($primary_invoice->date);
        $end = Carbon::parse($this->recurring_invoice->next_send_date_client);

        $this->setSubscriptionIntervalDuration($start->diffInSeconds($end));

        return $this;
    }

    /**
     * Determines if this subscription
     * is eligible for a refund.
     *
     * @return self
     */
    private function checkRefundPeriod(): self
    {
        if(!$this->subscription->refund_period || $this->subscription->refund_period === 0)
            return $this->setRefundable(false);
    
        $primary_invoice = $this->recurring_invoice
                                ->invoices()
                                ->where('is_deleted', 0)
                                ->where('is_proforma', 0)
                                ->orderBy('id', 'desc')
                                ->first();

        if($primary_invoice &&
        $primary_invoice->status_id == Invoice::STATUS_PAID &&
        Carbon::parse($primary_invoice->date)->addSeconds($this->subscription->refund_period)->lte(now()->startOfDay()->addSeconds($primary_invoice->client->timezone_offset()))
        ){
            return $this->setRefundable(true);
        }

        return $this->setRefundable(false);

    }

    /**
     * Gathers any unpaid invoices for this subscription.
     *
     * @return self
     */
    private function checkUnpaidInvoices(): self
    {
        $this->unpaid_invoices = $this->recurring_invoice
                                ->invoices()
                                ->where('is_deleted', 0)
                                ->where('is_proforma', 0)
                                ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                ->where('balance', '>', 0)
                                ->get();

        return $this;
    }
    
    private function setProRataRatio(int $ratio): self
    {
        $this->pro_rata_ratio = $ratio;

        return $this;
    }
    /**
     * setSubscriptionIntervalDuration
     *
     * @param  int $seconds
     * @return self
     */
    private function setSubscriptionIntervalDuration(int $seconds): self
    {
        $this->subscription_interval_duration = $seconds;

        return $this;
    }

    /**
     * setProRataDuration
     *
     * @param  int $seconds
     * @return self
     */
    private function setProRataDuration(int $seconds): self
    {
        $this->pro_rata_duration = $seconds;

        return $this;
    }
    
    /**
     * setRefundable
     *
     * @param  bool $refundable
     * @return self
     */
    private function setRefundable(bool $refundable): self
    {
        $this->refundable = $refundable;

        return $this;
    }

    /**
     * Determines if this users is in their trial period
     *
     * @return self
     */
    private function isInTrialPeriod(): self
    {

        if(!$this->subscription->trial_enabled) 
            return $this->setIsTrial(false);
            
        $primary_invoice = $this->recurring_invoice
                            ->invoices()
                            ->where('is_deleted', 0)
                            ->where('is_proforma', 0)
                            ->orderBy('id', 'asc')
                            ->first();
    
        if($primary_invoice && Carbon::parse($primary_invoice->date)->addSeconds($this->subscription->trial_duration)->lte(now()->startOfDay()->addSeconds($primary_invoice->client->timezone_offset())))
            return $this->setIsTrial(true);

        $this->setIsTrial(false);

        return $this;
    }

    /**
     * Sets the is_trial flag
     *
     * @param  bool $is_trial
     * @return self
     */
    private function setIsTrial(bool $is_trial): self
    {
        $this->is_trial = $is_trial;

        return $this;
    }
 
        
    /**
     * Getter for unpaid invoices
     *
     * @return \Illuminate\Database\Eloquent\Collection | null
     */
    public function getUnpaidInvoices(): ?\Illuminate\Database\Eloquent\Collection
    {
        return $this->unpaid_invoices;
    }

    /**
     * Gets the is_trial flag
     *
     * @return bool
     */
    public function getIsTrial(): bool
    {
        return $this->is_trial;
    }
    
    /**
     * Getter for refundable flag
     *
     * @return bool
     */
    public function getRefundable(): bool
    {
        return $this->refundable;
    }
    
    /**
     * The number of seconds used in the current duration
     *
     * @return int
     */
    public function getProRataDuration(): int
    {
        return $this->pro_rata_duration;
    }
    
    /**
     * The total number of seconds in this subscription interval
     *
     * @return int
     */
    public function getSubscriptionIntervalDuration(): int
    {
        return $this->subscription_interval_duration;
    }

        
    /**
     * Returns the pro rata ratio to be applied to any credit.
     *
     * @return int
     */
    public function getProRataRatio(): int
    {
        return $this->pro_rata_ratio;
    }
}