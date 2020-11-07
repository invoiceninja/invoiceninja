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

namespace App\Jobs\Entity;

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
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Swift_TransportException;
use Symfony\Component\Mime\Test\Constraint\EmailTextBodyContains;
use Turbo124\Beacon\Facades\LightLogs;

/*Multi Mailer implemented*/

class EmailEntity extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invitation;

    public $company;

    public $settings;

    public $entity_string;

    public $reminder_template;

    public $entity;

    public $html_engine;

    public $email_entity_builder;

    public $template_data;
    /**
     * EmailEntity constructor.
     * @param Invitation $invitation
     * @param Company    $company
     * @param ?string    $reminder_template
     */
    public function __construct($invitation, Company $company, ?string $reminder_template = null, $template_data = null)
    {
        $this->company = $company;

        $this->invitation = $invitation;

        $this->settings = $invitation->contact->client->getMergedSettings();

        $this->entity_string = $this->resolveEntityString();

        $this->entity = $invitation->{$this->entity_string};

        $this->reminder_template = $reminder_template ?: $this->entity->calculateTemplate($this->entity_string);

        $this->html_engine = new HtmlEngine($invitation);

        $this->template_data = $template_data;

        $this->email_entity_builder = $this->resolveEmailBuilder();

    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        if($this->company->is_disabled)
            return true;
        
        MultiDB::setDB($this->company->db);

        $this->setMailDriver();

        try {
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            Mail::to($this->invitation->contact->email, $this->invitation->contact->present()->name())
                ->send(
                    new TemplateEmail(
                        $this->email_entity_builder,
                        $this->invitation->contact->user,
                        $this->invitation->contact->client
                    )
                );
        } catch (Swift_TransportException $e) {
            $this->entityEmailFailed($e->getMessage());
        }

        if (count(Mail::failures()) > 0) {
            $this->logMailError(Mail::failures(), $this->entity->client);
        } else {
            $this->entityEmailSucceeded();
        }

        /* Mark entity sent */
        $this->entity->service()->markSent()->save();
    }

    public function failed($exception = null)
    {
        info('the job failed');

        $job_failure = new EmailInvoiceFailure();
        $job_failure->string_metric5 = $this->entity_string;
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

    private function resolveEmailBuilder()
    {
        $class = 'App\Mail\Engine\\' . ucfirst(Str::camel($this->entity_string)) . "EmailEngine";

        return (new $class($this->invitation, $this->reminder_template, $this->template_data))->build();
    }
}
