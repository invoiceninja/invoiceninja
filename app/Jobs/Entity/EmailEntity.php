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
use App\Models\CreditInvitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
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

class EmailEntity extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invitation;

    public $email_builder;

    public $company;

    public $settings;

    public $entity_string;

    /**
     * EmailEntity constructor.
     * @param InvoiceEmail $email_builder
     * @param Invitation $invitation
     */
    public function __construct($email_builder, $invitation, Company $company)
    {
        $this->company = $company;

        $this->invitation = $invitation;

        $this->email_builder = $email_builder;

        $this->settings = $invitation->contact->client->getMergedSettings();

        $this->entity_string = $this->resolveEntityString();
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
            Mail::to($this->invitation->contact->email, $this->invitation->contact->present()->name())
                ->send(
                    new TemplateEmail(
                        $this->email_builder,
                        $this->invitation->contact->user,
                        $this->invitation->contact->client
                    )
                );
        } catch (\Swift_TransportException $e) {
            $this->entityEmailFailed($e->getMessage());
        }

        if (count(Mail::failures()) > 0) {
            $this->logMailError(Mail::failures(), $this->invoice->client);
        } else {
            $this->entityEmailSucceeded();
        }

        /* Mark invoice sent */
        $this->invitation->invoice->service()->markSent()->save();
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

    private function resolveEntityString() :string
    {
        if($this->invitation instanceof InvoiceInvitation)
            return 'invoice';
        elseif($this->invitation instanceof QuoteInvitation)
            return 'quote';
        elseif($this->invitation instanceof CreditInvitation)
            return 'credit';
        elseif($this->invitation instanceof RecurringInvoiceInvitation)
            return 'recurring_invoice';
    }

    private function entityEmailFailed($message)
    {
        switch ($this->entity_string) {
            case 'invoice':
                event(new InvoiceWasEmailedAndFailed($this->invitation->invoice, $this->company, $message, Ninja::eventVars()));
                break;
            
            default:
                # code...
                break;
        }

    }

    private function entityEmailSucceeded()
    {
        switch ($this->entity_string) {
            case 'invoice':
                event(new InvoiceWasEmailed($this->invitation, $this->company, Ninja::eventVars()));
                break;
            
            default:
                # code...
                break;
        }
    }
}
