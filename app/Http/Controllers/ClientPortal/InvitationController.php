<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Events\Credit\CreditWasViewed;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Events\Quote\QuoteWasViewed;
use App\Http\Controllers\Controller;
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
        return $this->genericRouter($entity, $invitation_key);
    }

    public function recurringRouter(string $invitation_key)
    {
        return $this->genericRouter('recurring_invoice', $invitation_key);
    }

    private function genericRouter(string $entity, string $invitation_key)
    {
        $key = $entity.'_id';

        $entity_obj = 'App\Models\\'.ucfirst(Str::camel($entity)).'Invitation';

        $invitation = $entity_obj::whereRaw('BINARY `key`= ?', [$invitation_key])
                                    ->with('contact.client')
                                    ->firstOrFail();


        /* Return early if we have the correct client_hash embedded */

        if (request()->has('client_hash') && request()->input('client_hash') == $invitation->contact->client->client_hash) {
            nlog("scenario 1");
            auth()->guard('contact')->login($invitation->contact, true);
        } elseif ((bool) $invitation->contact->client->getSetting('enable_client_portal_password') !== false) {
            nlog("scenario 2");
            $this->middleware('auth:contact');
        } else {
            nlog("scenario 3");
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
                $invitation->invoice->service()->markSent()->save();
                event(new InvoiceWasViewed($invitation, $invitation->company, Ninja::eventVars()));
                break;
            case 'quote':
                $invitation->quote->service()->markSent()->save();
                event(new QuoteWasViewed($invitation, $invitation->company, Ninja::eventVars()));
                break;
            case 'credit':
                $invitation->credit->service()->markSent()->save();
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
