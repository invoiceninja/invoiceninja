<?php

namespace App\Jobs\Credit;

use App\Jobs\Payment\PaymentNotification;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Credit;
use App\Repositories\CreditRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StoreCredit implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $credit;

    protected $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Credit $credit, array $data)
    {
        $this->credit = $credit;

        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(CreditRepository $credit_repository): ?Credit
    {
        // MultiDB::setDB($this->company->db);

        // $payment = false;

        // if ($payment) {
        //     PaymentNotification::dispatch($payment, $payment->company);
        // }

        return $this->credit;
    }
}
