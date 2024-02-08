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
                
    private function calculateProRataRatio(): self
    {
        if($this->pro_rata_duration < $this->subscription_interval_duration)
            $this->setProRataRatio($this->pro_rata_duration/$this->subscription_interval_duration);

        return $this;
    }


    private function calculateSubscriptionIntervalDuration(): self
    {
       
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
    
        

}