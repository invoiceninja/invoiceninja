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
     * @var string
     */
    protected $notes;

    /**
     * @var string
     */
    protected $server;

    /**
     * Create a new job instance.

     * @param UserMailer    $userMailer
     * @param ContactMailer $contactMailer
     * @param PushService   $pushService
     * @param mixed         $user
     * @param mixed         $invoice
     * @param mixed         $type
     * @param mixed         $payment
     */
    public function __construct($user, $invoice, $type, $payment, $notes)
    {
        $this->user = $user;
        $this->invoice = $invoice;
        $this->type = $type;
        $this->payment = $payment;
        $this->notes = $notes;
        $this->server = config('database.default');
    }

    /**
     * Execute the job.
     *
     * @param ContactMailer $mailer
     */
    public function handle(UserMailer $userMailer)
    {
        if (config('queue.default') !== 'sync') {
            $this->user->account->loadLocalizationSettings();
        }

        $userMailer->sendNotification($this->user, $this->invoice, $this->type, $this->payment, $this->notes);
    }
}
