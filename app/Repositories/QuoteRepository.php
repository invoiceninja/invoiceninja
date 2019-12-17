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

use App\Factory\QuoteInvitationFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Quote\CreateQuoteInvitations;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use Illuminate\Http\Request;

/**
 * QuoteRepository
 */
class QuoteRepository extends BaseRepository
{


    public function getClassName()
    {
        return Quote::class;
    }
    
	public function save($data, Quote $quote) : ?Quote
	{
\Log::error("repo");
\Log::error($data);
        /* Always carry forward the initial invoice amount this is important for tracking client balance changes later......*/
        $starting_amount = $quote->amount;

        $quote->fill($data);

        $quote->save();

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


        if(isset($data['invitations']))
        {

            $invitations = collect($data['invitations']);

            /* Get array of Keyss which have been removed from the invitations array and soft delete each invitation */
            collect($quote->invitations->pluck('key'))->diff($invitations->pluck('key'))->each(function($invitation){

                QuoteInvitation::destroy($invitation);

            });


            foreach($data['invitations'] as $invitation)
            {
                $inv = false;

                if(array_key_exists ('key', $invitation))
                    $inv = QuoteInvitation::whereKey($invitation['key'])->first();

                if(!$inv)
                {
                    $invitation['client_contact_id'] = $this->decodePrimaryKey($invitation['client_contact_id']);

                    $new_invitation = QuoteInvitationFactory::create($quote->company_id, $quote->user_id);
                    $new_invitation->fill($invitation);
                    $new_invitation->quote_id = $quote->id;
                    $new_invitation->save();

                }
            }

        }

        /* If no invitations have been created, this is our fail safe to maintain state*/
        if($quote->invitations->count() == 0)
            CreateQuoteInvitations::dispatchNow($quote);

        $quote = $quote->calc()->getInvoice();
        
        $quote->save();

        $finished_amount = $quote->amount;
//todo need answers on this
//        $quote = ApplyInvoiceNumber::dispatchNow($quote, $quote->client->getMergedSettings());

        return $quote->fresh();
	}

}