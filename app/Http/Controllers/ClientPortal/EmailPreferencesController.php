<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Admin\ClientUnsubscribedObject;
use App\Models\ClientContact;
use Illuminate\Http\Request;

class EmailPreferencesController extends Controller
{
    public function index(ClientContact $clientContact, Request $request): \Illuminate\View\View
    {
        if (!$request->hasValidSignature()) {
            abort(404);
        }

        $data['recieve_emails'] = $clientContact->is_locked ? false : true;
        $data['logo'] = $clientContact->company->present()->logo();

        return $this->render('generic.email_preferences', $data);
    }

    public function update(ClientContact $clientContact, Request $request): \Illuminate\Http\RedirectResponse
    {
        if (!$request->hasValidSignature()) {
            abort(404);
        }

        $clientContact->is_locked = $request->has('recieve_emails') ? false : true;
        $clientContact->save();

        if ($clientContact->is_locked) {
            $nmo = new NinjaMailerObject();
            $nmo->mailable = new NinjaMailer((new ClientUnsubscribedObject($clientContact, $clientContact->company))->build());
            $nmo->company = $clientContact->company;
            $nmo->to_user = $clientContact->company->owner();
            $nmo->settings = $clientContact->company->settings;

            (new NinjaMailerJob($nmo))->handle();
        }

        return back()->with('message', ctrans('texts.updated_settings'));
    }
}

