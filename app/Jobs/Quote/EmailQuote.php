<?php

namespace App\Jobs\Quote;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Quote\QuoteWasEmailed;
use App\Events\Quote\QuoteWasEmailedAndFailed;
use App\Jobs\Utils\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;

;

use App\Models\Company;
use App\Models\Quote;
use App\Models\QuoteInvitation;
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

    public $quote_invitation;

    public $email_builder;

    /**
     * EmailQuote constructor.
     * @param BuildEmail $email_builder
     * @param QuoteInvitation $quote_invitation
     */
    public function __construct($email_builder, QuoteInvitation $quote_invitation)
    {
        $this->quote_invitation = $quote_invitation;
        $this->email_builder = $email_builder;
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

        Mail::to($this->quote_invitation->contact->email, $this->quote_invitation->contact->present()->name())
            ->send(new TemplateEmail($email_builder,
                    $this->quote_invitation->contact->user,
                    $this->quote_invitation->contact->client
                )
            );

        if (count(Mail::failures()) > 0) {
            return $this->logMailError($errors);
        }
    }

    private function logMailError($errors)
    {
        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $this->quote->client
        );
    }
}
