<?php

namespace App\Jobs\Invoice;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Helpers\Email\BuildEmail;
use App\Jobs\Utils\SystemLogger;
use App\Mail\TemplateEmail;
use App\Models\Company;
use App\Models\Invoice;
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

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, Company $company, BuildEmail $email_builder)
    {
        $this->invoice = $invoice;
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

        //todo - change runtime config of mail driver if necessary

        $email_builder = $this->email_builder;

        foreach ($email_builder->getRecipients() as $recipient) {
            Mail::to($recipient['email'], $recipient['name'])
                ->send(new TemplateEmail($email_builder,
                        $this->quote->user,
                        $this->quote->client
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
            $this->invoice->client
        );
    }
}
