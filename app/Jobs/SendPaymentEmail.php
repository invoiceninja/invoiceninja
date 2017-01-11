<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Ninja\Mailers\ContactMailer;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Monolog\Logger;
use Carbon;

/**
 * Class SendInvoiceEmail
 */
class SendPaymentEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var Payment
     */
    protected $payment;


    /**
     * Create a new job instance.

     * @param Payment $payment
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(ContactMailer $contactMailer)
    {
        $contactMailer->sendPaymentConfirmation($this->payment);
    }


}
