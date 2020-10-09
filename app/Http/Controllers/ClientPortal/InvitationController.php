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

namespace App\Http\Controllers\ClientPortal;

use App\Events\Credit\CreditWasViewed;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Events\Quote\QuoteWasViewed;
use App\Http\Controllers\Controller;
use App\Models\InvoiceInvitation;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Class InvitationController.
 */
class InvitationController extends Controller
{
    use MakesHash;
    use MakesDates;

    public function router(string $entity, string $invitation_key)
    {

        $key = $entity.'_id';

        $entity_obj = 'App\Models\\'.ucfirst(Str::camel($entity)).'Invitation'; //todo sensitive to the route parameters here

        $invitation = $entity_obj::whereRaw('BINARY `key`= ?', [$invitation_key])
                                    ->with('contact.client')
                                    ->firstOrFail();

        /* Return early if we have the correct client_hash embedded */

        if(request()->has('client_hash') && request()->input('client_hash') == $invitation->contact->client->client_hash) {
            auth()->guard('contact')->login($invitation->contact, true);
        }
        else if ((bool) $invitation->contact->client->getSetting('enable_client_portal_password') !== false) {
            $this->middleware('auth:contact');
        } 
        else {
            auth()->guard('contact')->login($invitation->contact, true);
        }

        if (auth()->guard('contact') && ! request()->has('silent') && ! $invitation->viewed_date) {

            $invitation->markViewed();

            event(new InvitationWasViewed($invitation->{$entity}, $invitation, $invitation->{$entity}->company, Ninja::eventVars()));

            $this->fireEntityViewedEvent($invitation, $entity);
        }

        return redirect()->route('client.'.$entity.'.show', [$entity => $this->encodePrimaryKey($invitation->{$key})]);


    }

    private function fireEntityViewedEvent($invitation, $entity_string)
    {
        switch ($entity_string) {
            case 'invoice':
                event(new InvoiceWasViewed($invitation, $invitation->company, Ninja::eventVars()));
                break;
            case 'quote':
                event(new QuoteWasViewed($invitation, $invitation->company, Ninja::eventVars()));
                break;
            case 'credit':
                event(new CreditWasViewed($invitation, $invitation->company, Ninja::eventVars()));
                break;
            default:
                // code...
                break;
        }
    }

    public function routerForDownload(string $entity, string $invitation_key)
    {
        return redirect('client/'.$entity.'/'.$invitation_key.'/download_pdf');
    }

    public function routerForIframe(string $entity, string $client_hash, string $invitation_key)
    {
    }
}
