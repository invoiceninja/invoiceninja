<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Invoice;

use App\DataMapper\Analytics\EmailInvoiceFailure;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Mail\BaseMailerJob;
use App\Jobs\Utils\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\SystemLog;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Test\Constraint\EmailTextBodyContains;
use Turbo124\Beacon\Facades\LightLogs;

/*Multi Mailer implemented*/

class EmailInvoice extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice_invitation;

    public $email_builder;

    public $company;

    public $settings;

    /**
     * EmailInvoice constructor.
     * @param InvoiceEmail $email_builder
     * @param InvoiceInvitation $quote_invitation
     */
    public function __construct(InvoiceEmail $email_builder, InvoiceInvitation $invoice_invitation, Company $company)
    {
        $this->company = $company;

        $this->invoice_invitation = $invoice_invitation;

        $this->email_builder = $email_builder;

        $this->settings = $invoice_invitation->contact->client->getMergedSettings();
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        MultiDB::setDB($this->company->db);

        $this->setMailDriver();

        try {
            Mail::to($this->invoice_invitation->contact->email, $this->invoice_invitation->contact->present()->name())
                ->send(
                    new TemplateEmail(
                        $this->email_builder,
                        $this->invoice_invitation->contact->user,
                        $this->invoice_invitation->contact->client
                    )
                );
        } catch (\Swift_TransportException $e) {
            event(new InvoiceWasEmailedAndFailed($this->invoice_invitation->invoice, $this->company, $e->getMessage(), Ninja::eventVars()));
        }

        if (count(Mail::failures()) > 0) {
            $this->logMailError(Mail::failures(), $this->invoice->client);
        } else {
            event(new InvoiceWasEmailed($this->invoice_invitation, $this->company, Ninja::eventVars()));
        }

        /* Mark invoice sent */
        $this->invoice_invitation->invoice->service()->markSent()->save();
    }

    public function failed($exception = null)
    {
        info('the job failed');

        $job_failure = new EmailInvoiceFailure();
        $job_failure->string_metric5 = get_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->batch();

    }
}
