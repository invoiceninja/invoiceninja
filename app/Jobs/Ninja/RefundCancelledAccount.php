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
namespace App\Jobs\Ninja;

use App\Models\Account;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class RefundCancelledAccount implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $account;

    /**
     * Create a new job instance.
     *
     * @param Account $account
     */
    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // if free plan, return
        if (Ninja::isSelfHost() || $this->account->isFreeHostedClient()) {
            return;
        }

        $plan_details = $this->account->getPlanDetails();

        if (! $plan_details) {
            return;
        }

        /* Trial user cancelling early.... */
        if ($plan_details['trial_plan']) {
            return;
        }

        /* Is the plan Active? */
        if (! $plan_details['active']) {
            return;
        }

        /* Refundable client! */

        $plan_start = $plan_details['started'];
        $plan_expires = $plan_details['expires'];
        $paid = $plan_details['paid'];
        $term = $plan_details['term'];

        $refund = $this->calculateRefundAmount($paid, $plan_expires);

    }

    private function calculateRefundAmount($amount, $plan_expires)
    {
        $end_date = Carbon::parse($plan_expires);
        $now = Carbon::now();

        $days_left = intval(abs($now->diffInDays($end_date)));

        $pro_rata_ratio = $days_left / 365;

        $pro_rata_refund = $amount * $pro_rata_ratio;

        return $pro_rata_refund;
    }
}
