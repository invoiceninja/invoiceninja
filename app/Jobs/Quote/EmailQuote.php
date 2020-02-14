<?php

namespace App\Jobs\Quote;

use App\Account;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Quote\QuoteWasEmailed;
use App\Events\Quote\QuoteWasEmailedAndFailed;
use App\Helpers\Email\BuildEmail;
use App\Jobs\Utils\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Quote;
use App\SystemLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EmailQuote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $quote;

    public $email_builder;

    private $account;

    /**
     * EmailQuote constructor.
     * @param Quote $quote
     * @param Account $account
     */
    public function __construct(Quote $quote, Account $account, BuildEmail $email_builder)
    {
        $this->quote = $quote;
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
        $email_builder = $this->email_builder;

        $this->quote->invitations->each(function ($invitation) use ($email_builder) {
            if ($invitation->contact->email) {
                $email_builder->setFooter("<a href='{$invitation->getLink()}'>Invoice Link</a>");

                //send message
                Mail::to($invitation->contact->email, $invitation->contact->present()->name())
                    ->send(new TemplateEmail($email_builder, $invitation->contact->user,
                        $invitation->contact->customer));

                if (count(Mail::failures()) > 0) {
                    event(new QuoteWasEmailedAndFailed($this->quote, Mail::failures()));

                    return $this->logMailError($errors);
                }

                //fire any events
                event(new QuoteWasEmailed($this->quote));

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
            $this->quote->customer
        );
    }
}
