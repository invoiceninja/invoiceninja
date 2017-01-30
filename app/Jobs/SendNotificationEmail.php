<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Ninja\Mailers\UserMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class SendInvoiceEmail.
 */
class SendNotificationEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var Invoice
     */
    protected $invoice;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var Payment
     */
    protected $payment;

    /**
     * Create a new job instance.

     * @param UserMailer    $userMailer
     * @param ContactMailer $contactMailer
     * @param PushService   $pushService
     */
    public function __construct($user, $invoice, $type, $payment)
    {
        $this->user = $user;
        $this->invoice = $invoice;
        $this->type = $type;
        $this->payment = $payment;
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(UserMailer $userMailer)
    {
        $userMailer->sendNotification($this->user, $this->invoice, $this->type, $this->payment);
    }
}
