<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ClientContact;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Http\Controllers\Controller;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Support\Facades\Cache;
use App\Mail\Admin\ClientUnsubscribedObject;

class EmailPreferencesController extends Controller
{
    public function index(string $entity, string $invitation_key, Request $request): \Illuminate\View\View
    {
        $class = "\\App\\Models\\".ucfirst(Str::camel($entity)).'Invitation';
        $invitation = $class::where('key', $invitation_key)->firstOrFail();

        $data['receive_emails'] = $invitation->contact->is_locked ? false : true;
        $data['company'] = $invitation->company;

        return $this->render('generic.email_preferences', $data);
    }

    public function update(string $entity, string $invitation_key, Request $request): \Illuminate\Http\RedirectResponse
    {
        $class = "\\App\\Models\\" . ucfirst(Str::camel($entity)) . 'Invitation';
        $invitation = $class::withTrashed()->where('key', $invitation_key)->firstOrFail();

        $invitation->contact->is_locked = $request->action === 'unsubscribe' ? true : false;
        $invitation->contact->push();

        if ($invitation->contact->is_locked && !Cache::has("unsubscribe_notification_suppression:{$invitation_key}")) {
            $nmo = new NinjaMailerObject();
            $nmo->mailable = new NinjaMailer((new ClientUnsubscribedObject($invitation->contact, $invitation->contact->company, true))->build());
            $nmo->company = $invitation->contact->company;
            $nmo->to_user = $invitation->contact->company->owner();
            $nmo->settings = $invitation->contact->company->settings;

            NinjaMailerJob::dispatch($nmo);

            Cache::put("unsubscribe_notification_suppression:{$invitation_key}", true, 3600);
        }

        return back()->with('message', ctrans('texts.updated_settings'));
    }
}
