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

namespace App\Repositories;

use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Factory\InvoiceInvitationFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\UpdateCompanyLedgerWithInvoice;
use App\Jobs\Invoice\ApplyInvoiceNumber;
use App\Jobs\Invoice\CreateInvoiceInvitations;
use App\Jobs\Product\UpdateOrCreateProduct;
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
     * @param      InvoiceSum|\App\Models\Invoice               $invoice  The invoice
     *
     * @return     Invoice|InvoiceSum|\App\Models\Invoice|null  Returns the invoice object
     */
    public function save($data, Invoice $invoice) : ?Invoice
    {

        /* Always carry forward the initial invoice amount this is important for tracking client balance changes later......*/
        $starting_amount = $invoice->amount;

        $invoice->fill($data);

        $invoice->save();

        if (isset($data['client_contacts'])) {
            foreach ($data['client_contacts'] as $contact) {
                if ($contact['send_invoice'] == 1) {
                    $client_contact = ClientContact::find($this->decodePrimaryKey($contact['id']));
                    $client_contact->send_invoice = true;
                    $client_contact->save();
                }
            }
        }


        if (isset($data['invitations'])) {
            $invitations = collect($data['invitations']);

            /* Get array of Keyss which have been removed from the invitations array and soft delete each invitation */
            collect($invoice->invitations->pluck('key'))->diff($invitations->pluck('key'))->each(function ($invitation) {
                InvoiceInvitation::destroy($invitation);
            });


            foreach ($data['invitations'] as $invitation) {
                $inv = false;

                if (array_key_exists('key', $invitation)) {
                    $inv = InvoiceInvitation::whereKey($invitation['key'])->first();
                }

                if (!$inv) {

                    $new_invitation = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
                    $new_invitation->fill($invitation);
                    $new_invitation->invoice_id = $invoice->id;
                    $new_invitation->client_contact_id = $this->decodePrimaryKey($invitation['client_contact_id']);
                    $new_invitation->save();
                }
            }
        }

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if ($invoice->invitations->count() == 0) {
            CreateInvoiceInvitations::dispatchNow($invoice, $invoice->company);
        }

        $invoice = $invoice->calc()->getInvoice();
        
        $invoice->save();

        $finished_amount = $invoice->amount;

        /**/
        if (($finished_amount != $starting_amount) && ($invoice->status_id != Invoice::STATUS_DRAFT)) {
            UpdateCompanyLedgerWithInvoice::dispatchNow($invoice, ($finished_amount - $starting_amount), $invoice->company);
        }

        $invoice = ApplyInvoiceNumber::dispatchNow($invoice, $invoice->client->getMergedSettings(), $invoice->company);

        if ($invoice->company->update_products !== false) {
            UpdateOrCreateProduct::dispatch($invoice->line_items, $invoice, $invoice->company);
        }

        return $invoice->fresh();
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
        return $invoice->markSent();
    }
}
