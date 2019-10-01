<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Repositories;

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Factory\InvoiceInvitationFactory;
use App\Helpers\Invoice\InvoiceCalc;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Listeners\Invoice\CreateInvoiceInvitation;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * InvoiceRepository
 */
class InvoiceRepository extends BaseRepository
{
    use MakesHash;
    /**
     * Gets the class name.
     *
     * @return     string  The class name.
     */
    public function getClassName()
    {

        return Invoice::class;

    }
    
	/**
     * Saves the invoices
     *
     * @param      array.                                        $data     The invoice data
     * @param      InvoiceCalc|\App\Models\Invoice               $invoice  The invoice
     *
     * @return     Invoice|InvoiceCalc|\App\Models\Invoice|null  Returns the invoice object
     */
    public function save($data, Invoice $invoice) : ?Invoice
	{

        /* Always carry forward the initial invoice amount this is important for tracking client balance changes later......*/
        $starting_amount = $invoice->amount;

        $invoice->fill($data);

        $invoice->save();

        if(isset($data['client_contacts']))
        {
            foreach($data['client_contacts'] as $contact)
            {
                if($contact['send_invoice'] == 1)
                {
                    $client_contact = ClientContact::find($this->decodePrimaryKey($contact['id']));
                    $client_contact->send_invoice = true;
                    $client_contact->save();
                }
            }
        }

        event(new CreateInvoiceInvitation($invoice));

        $invoice_calc = new InvoiceCalc($invoice, $invoice->settings);

        $invoice = $invoice_calc->build()->getInvoice();
        
        $invoice->save();

        $finished_amount = $invoice->amount;

        if($finished_amount != $starting_amount)
            UpdateCompanyLedgerWithInvoice::dispatchNow($invoice, ($finished_amount - $starting_amount));

        return $invoice;

	}

    /**
     * Mark the invoice as sent.
     *
     * @param      \App\Models\Invoice               $invoice  The invoice
     *
     * @return     Invoice|\App\Models\Invoice|null  Return the invoice object
     */
    public function markSent(Invoice $invoice) : ?Invoice
    {
        /* Return immediately if status is not draft */
        if($invoice->status_id != Invoice::STATUS_DRAFT)
            return $invoice;

        $invoice->status_id = Invoice::STATUS_SENT;

        $this->markInvitationsSent();

        $invoice->save();
\Log::error("marking sent");
        UpdateCompanyLedgerWithInvoice::dispatchNow($this->invoice, $this->balance);

        return $invoice;

    }


    /**
     * Updates Invites to SENT
     *
     * @param      \App\Models\Invoice  $invoice  The invoice
     */
    private function markInvitationsSent(Invoice $invoice) :void
    {
        $invoice->invitations->each(function($invitation) {

            if(!isset($invitation->sent_date))
            {
                $invitation->sent_date = Carbon::now();
                $invitation->save();
            }

        });
    }

}