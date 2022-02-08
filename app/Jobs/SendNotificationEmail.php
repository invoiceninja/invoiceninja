<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Traits\SerialisesDeletedModels;
use App\Models\User;
use App\Ninja\Mailers\UserMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Class SendInvoiceEmail.
 */
class SendNotificationEmail extends Job implements ShouldQueue
{
    use InteractsWithQueue;

    public $deleteWhenMissingModels = true;

    /**
     * @var User
     */
    public User $user;

    /**
     * @var Invoice
     */
    public Invoice $invoice;

    /**
     * @var string
     */
    public $type;

    /**
     * @var Payment
     */
    public ?Payment $payment;

    /**
     * @var string
     */
    public $notes;

    /**
     * @var string
     */
    public $server;

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
    public function __construct(User $user, Invoice $invoice, $type, ?Payment $payment, $notes)
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
