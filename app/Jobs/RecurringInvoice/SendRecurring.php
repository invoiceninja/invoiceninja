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

namespace App\Jobs\RecurringInvoice;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Factory\RecurringInvoiceToInvoiceFactory;
use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Invoice\EmailInvoice;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class SendRecurring implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use GeneratesCounter;

    public $recurring_invoice;

    protected $db;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(RecurringInvoice $recurring_invoice, string $db = 'db-ninja-01')
    {
        $this->recurring_invoice = $recurring_invoice;
        $this->db = $db;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {

        // Generate Standard Invoice
        $invoice = RecurringInvoiceToInvoiceFactory::create($this->recurring_invoice, $this->recurring_invoice->client);
        $invoice = $invoice->service()
                           ->markSent()
                           ->applyRecurringNumber()
                           ->createInvitations()
                           ->save();

       $invoice->invitations->each(function ($invitation) use ($invoice) {

            $email_builder = (new InvoiceEmail())->build($invitation);

            EmailInvoice::dispatch($email_builder, $invitation, $invoice->company);

            info("Firing email for invoice {$invoice->number}");

        });

        /* Set next date here to prevent a recurring loop forming */
        $this->recurring_invoice->next_send_date = $this->recurring_invoice->nextSendDate()->format('Y-m-d');
        $this->recurring_invoice->remaining_cycles = $this->recurring_invoice->remainingCycles();
        $this->recurring_invoice->last_sent_date = date('Y-m-d');

        /* Set completed if we don't have any more cycles remaining*/
        if ($this->recurring_invoice->remaining_cycles == 0) 
            $this->recurring_invoice->setCompleted();

        $this->recurring_invoice->save();

        if ($invoice->invitations->count() > 0) 
            event(new InvoiceWasEmailed($invoice->invitations->first(), $invoice->company, Ninja::eventVars()));

        // Fire Payment if auto-bill is enabled
        if ($this->recurring_invoice->auto_bill) 
            $invoice->service()->autoBill()->save();

    }

}
