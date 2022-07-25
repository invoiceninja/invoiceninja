<?php
/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Credit;

use App\Models\Credit;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ApplyCreditPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $credit;

    public $payment;

    public $amount;

    /**
     * Create a new job instance.
     *
     * @param Credit $credit
     * @param Payment $payment
     * @param float $amount
     */
    public function __construct(Credit $credit, Payment $payment, float $amount)
    {
        $this->credit = $credit;
        $this->payment = $payment;
        $this->amount = $amount;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        /* Update Pivot Record amount */
        $this->payment->credits->each(function ($cred) {
            if ($cred->id == $this->credit->id) {
                $cred->pivot->amount = $this->amount;
                $cred->pivot->save();

                $cred->paid_to_date += $this->amount;
                $cred->save();
            }
        });

        $credit_balance = $this->credit->balance;

        if ($this->amount == $credit_balance) { //total credit applied.

            $this->credit
                ->service()
                ->markSent()
                ->setStatus(Credit::STATUS_APPLIED)
                ->adjustBalance($this->amount * -1)
                ->updatePaidToDate($this->amount)
                ->save();
        } elseif ($this->amount < $credit_balance) { //compare number appropriately

            $this->credit
                ->service()
                ->markSent()
                ->setStatus(Credit::STATUS_PARTIAL)
                ->adjustBalance($this->amount * -1)
                ->updatePaidToDate($this->amount)
                ->save();
        }

        /* Update Payment Applied Amount*/
        $this->payment->save();
    }
}
