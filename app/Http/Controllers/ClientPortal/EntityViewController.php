<?php

namespace App\Http\Controllers\ClientPortal;

use App\Events\Credit\CreditWasViewed;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Events\Quote\QuoteWasViewed;
use App\Http\Controllers\Controller;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EntityViewController extends Controller
{
    use MakesHash;

    /**
     * Available options for viewing.
     *
     * @var array
     */
    private $entity_types = ['invoice', 'quote', 'credit', 'recurring_invoice'];

    /**
     * Show the entity outside client portal.
     *
     * @param string $entity_type
     * @param string $invitation_key
     * @return Factory|View
     */
    public function index(string $entity_type, string $invitation_key)
    {
        if (! in_array($entity_type, $this->entity_types)) {
            abort(404, 'Entity not found');
        }

        $invitation_entity = sprintf('App\\Models\\%sInvitation', ucfirst($entity_type));

        $key = $entity_type.'_id';

        $invitation = $invitation_entity::where('key', $invitation_key)
                                        ->with('contact.client')
                                        ->firstOrFail();

        // $invitation = $invitation_entity::whereRaw('BINARY `key`= ?', [$invitation_key])
        //                                 ->with('contact.client')
        //                                 ->firstOrFail();

        $contact = $invitation->contact;
        $client = $contact->client;
        $entity = $invitation->{$entity_type};

        if (is_null($contact->password) || empty($contact->password)) {
            return redirect("/client/password/reset?email={$contact->email}");
        }

        if ((bool) $invitation->contact->client->getSetting('enable_client_portal_password') !== false) {
            session()->flash("{$entity_type}_VIEW_{$entity->hashed_id}", true);
        }

        if (! session("{$entity_type}_VIEW_{$entity->hashed_id}")) {
            return redirect()->route('client.entity_view.password', compact('entity_type', 'invitation_key'));
        }

        return $this->render('view_entity.index', [
            'root' => 'themes',
            'entity' => $entity,
        ]);
    }

    /**
     * Show the form for entering password.
     *
     * @param string $entity_type
     * @param string $invitation_key
     *
     * @return Factory|View
     */
    public function password(string $entity_type, string $invitation_key)
    {
        return $this->render('view_entity.password', [
            'root' => 'themes',
            'entity_type' => $entity_type,
        ]);
    }

    /**`
     * Handle the password check.
     *
     * @param string $entity_type
     * @param string $invitation_key
     *
     * @return Redirector|RedirectResponse|mixed
     */
    public function handlePassword(string $entity_type, string $invitation_key)
    {
        if (! in_array($entity_type, $this->entity_types)) {
            abort(404, 'Entity not found');
        }

        $invitation_entity = sprintf('App\\Models\\%sInvitation', ucfirst($entity_type));

        $key = $entity_type.'_id';

        $invitation = $invitation_entity::where('key', $invitation_key)->firstOrFail();
        // $invitation = $invitation_entity::whereRaw('BINARY `key`= ?', [$invitation_key])->firstOrFail();

        $contact = $invitation->contact;

        $check = Hash::check(request()->password, $contact->password);

        $entity_class = sprintf('App\\Models\\%s', ucfirst($entity_type));

        $entity = $entity_class::findOrFail($invitation->{$key});

        if ($check) {
            session()->flash("{$entity_type}_VIEW_{$entity->hashed_id}", true);

            return redirect()->route('client.entity_view', compact('entity_type', 'invitation_key'));
        }

        session()->flash('PASSWORD_FAILED', true);

        return back();
    }

    public function handlePasswordSet(Request $request)
    {
        $entity_obj = 'App\Models\\'.ucfirst(Str::camel($request->entity_type)).'Invitation';
        $key = $request->entity_type.'_id';

        $invitation = $entity_obj::where('key', $request->invitation_key)
                                    ->whereHas($request->entity_type, function ($query) {
                                        $query->where('is_deleted', 0);
                                    })
                                    ->with('contact.client')
                                    ->first();

        $contact = $invitation->contact;
        $contact->password = Hash::make($request->password);
        $contact->save();

        $request->session()->invalidate();
        auth()->guard('contact')->loginUsingId($contact->id, true);

        if (! $invitation->viewed_date) {
            $invitation->markViewed();

            if (! session()->get('is_silent')) {
                event(new InvitationWasViewed($invitation->{$request->entity_type}, $invitation, $invitation->{$request->entity_type}->company, Ninja::eventVars()));
            }

            if (! session()->get('is_silent')) {
                $this->fireEntityViewedEvent($invitation, $request->entity_type);
            }
        }

        return redirect()->route('client.'.$request->entity_type.'.show', [$request->entity_type => $this->encodePrimaryKey($invitation->{$key})]);
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
}
