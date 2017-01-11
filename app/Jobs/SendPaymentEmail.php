<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Ninja\Mailers\ContactMailer;
use App\Ninja\Mailers\UserMailer;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Services\PushService;
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

     * @param UserMailer $userMailer
     * @param ContactMailer $contactMailer
     * @param PushService $pushService
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
    public function handle(UserMailer $userMailer, ContactMailer $contactMailer, PushService $pushService)
    {
        $payment = $this->payment;
        $invoice = $payment->invoice;

        $contactMailer->sendPaymentConfirmation($payment);

        $payment->account->load('users');
        foreach ($payment->account->users as $user)
        {
            if ($user->notify_paid)
            {
                $userMailer->sendNotification($user, $invoice, 'paid', $payment);
            }
        }

        $pushService->sendNotification($invoice, 'paid');
    }

    /**
     * Handle a job failure.
     *
     * @param ContactMailer $mailer
     * @param Logger $logger
     */
     /*
    public function failed(ContactMailer $mailer, Logger $logger)
    {
        $this->jobName = $this->job->getName();

        parent::failed($mailer, $logger);
    }
    */
}
