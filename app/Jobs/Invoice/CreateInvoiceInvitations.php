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

use App\Factory\InvoiceInvitationFactory;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class CreateInvoiceInvitations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $invoice;

    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, Company $company)
    {
        $this->invoice = $invoice;
        $this->company = $company;
    }

    public function handle()
    {
        MultiDB::setDB($this->company->db);

        $contacts = $this->invoice->client->contacts;

        $contacts->each(function ($contact) {
            $invitation = InvoiceInvitation::whereCompanyId($this->invoice->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereInvoiceId($this->invoice->id)
                                        ->first();

            if (!$invitation && $contact->send_invoice) {
                $ii = InvoiceInvitationFactory::create($this->invoice->company_id, $this->invoice->user_id);
                $ii->invoice_id = $this->invoice->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && !$contact->send_invoice) {
                $invitation->delete();
            }
        });
    }
}
