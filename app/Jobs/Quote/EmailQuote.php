<?php

namespace App\Jobs\Quote;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Quote\QuoteWasEmailed;
use App\Events\Quote\QuoteWasEmailedAndFailed;
use App\Helpers\Email\BuildEmail;
use App\Jobs\Utils\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;

;

use App\Models\Company;
use App\Models\Quote;
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

    private $company;

    /**
     * EmailQuote constructor.
     * @param Quote $quote
     * @param Account $account
     */
    public function __construct(Quote $quote, Company $company, BuildEmail $email_builder)
    {
        $this->quote = $quote;
        $this->email_builder = $email_builder;
        $this->company = $company;
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

        foreach ($email_builder->getRecipients() as $recipient) {
            Mail::to($recipient['email'], $recipient['name'])
                ->send(new TemplateEmail($email_builder,
                        $this->quote->user,
                        $this->quote->customer
                    )
                );

            if (count(Mail::failures()) > 0) {
                return $this->logMailError($errors);
            }
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
