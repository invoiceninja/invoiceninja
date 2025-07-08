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

use Illuminate\View\View;
use App\Models\ClientContact;
use App\Jobs\Util\UploadAvatar;
use Illuminate\Routing\Redirector;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use App\Http\Requests\ClientPortal\UpdateClientRequest;
use App\Http\Requests\ClientPortal\UpdateContactRequest;

class ProfileController extends Controller
{
    /**
     * Show the form for editing the specified resource.
     *
     * @param ClientContact $client_contact
     * @return Factory|View|RedirectResponse|Redirector
     */
    public function edit(ClientContact $client_contact)
    {
        if (auth()->guard('contact')->user()->client->getSetting('enable_client_profile_update') === false) {
            return redirect()->route('client.dashboard');
        }

        return $this->render('profile.index');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateContactRequest $request
     * @param ClientContact $client_contact
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateContactRequest $request, ClientContact $client_contact)
    {
        $client_contact->fill($request->all());

        if ($request->has('password')) {
            $client_contact->password = encrypt($request->password);
        }

        $client_contact->save();

        return back()->withSuccess(
            ctrans('texts.profile_updated_successfully')
        );
    }

    public function updateClient(UpdateClientRequest $request, ClientContact $client_contact)
    {
        $client = $client_contact->client;

        //update avatar if needed
        if ($request->file('logo')) {
            $path = (new UploadAvatar($request->file('logo'), $client->client_hash))->handle();

            if ($path) {
                $client->logo = $path;
            }
        }

        $client->fill($request->all());
        $client->save();

        return back()->withSuccess(
            ctrans('texts.profile_updated_successfully')
        );
    }
}
