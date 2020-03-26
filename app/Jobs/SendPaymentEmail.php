<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Ninja\Mailers\ContactMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class SendInvoiceEmail.
 */
class SendPaymentEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var Payment
     */
    protected $payment;

    /**
     * @var string
     */
    protected $server;

    /**
     * Create a new job instance.

     * @param Payment $payment
     */
    public function __construct($payment)
    {
        $this->payment = $payment;
        $this->server = config('database.default');
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
