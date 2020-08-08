<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Invoice;

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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Test\Constraint\EmailTextBodyContains;

/*Multi Mailer implemented*/

class EmailInvoice extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice_invitation;

    public $email_builder;

    public $company;
    /**
     *
     * EmailInvoice constructor.
     * @param InvoiceEmail $email_builder
     * @param InvoiceInvitation $quote_invitation
     */

    public function __construct(InvoiceEmail $email_builder, InvoiceInvitation $invoice_invitation, Company $company)
    {
        $this->company = $company;

        $this->invoice_invitation = $invoice_invitation;

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
        MultiDB::setDB($this->company->db);
        
        $this->setMailDriver($this->invoice_invitation->invoice->client->getSetting('email_sending_method'));

        Mail::to($this->invoice_invitation->contact->email, $this->invoice_invitation->contact->present()->name())
            ->send(
                new TemplateEmail(
                    $this->email_builder,
                    $this->invoice_invitation->contact->user,
                    $this->invoice_invitation->contact->client
                )
            );

        if (count(Mail::failures()) > 0) {
            return $this->logMailError(Mail::failures(), $this->invoice->client);
        }
    }


}
