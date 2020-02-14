<?php

namespace App\Jobs\Invoice;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Helpers\Email\BuildEmail;
use App\Jobs\Utils\SystemLogger;
use App\Mail\TemplateEmail;
use App\Invoice;
use App\Account;
use App\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Test\Constraint\EmailTextBodyContains;

class EmailInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public $email_builder;

    private $account;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, Account $account, BuildEmail $email_builder)
    {
        $this->invoice = $invoice;
        $this->email_builder = $email_builder;
        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        //todo - change runtime config of mail driver if necessary

        $email_builder = $this->email_builder;

        $this->invoice->invitations->each(function ($invitation) use ($email_builder) {
            if ($invitation->contact->send_invoice && $invitation->contact->email) {
                $email_builder->setFooter("<a href='{$invitation->getLink()}'>Invoice Link</a>");

                //change the runtime config of the mail provider here:

                //send message
                Mail::to($invitation->contact->email, $invitation->contact->present()->name())
                    ->send(new TemplateEmail($email_builder,
                        $invitation->contact->user,
                        $invitation->contact->customer));

                if (count(Mail::failures()) > 0) {
                    event(new InvoiceWasEmailedAndFailed($this->invoice, Mail::failures()));

                    return $this->logMailError($errors);
                }

                //fire any events
                event(new InvoiceWasEmailed($this->invoice));

                //sleep(5);
            }
        });
    }

    private function logMailError($errors)
    {
        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $this->invoice->customer
        );
    }
}
