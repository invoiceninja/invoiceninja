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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\UpdateContactRequest;
use App\Http\Requests\ClientPortal\UpdateSettingsRequest;
use App\Jobs\Util\UploadAvatar;
use App\Models\ClientContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(ClientContact $client_contact)
    {
        /* Dropzone configuration */
        $data = [
            'params' => [
                'is_avatar' => TRUE,
            ],
            'url' => '/client/document',
            'multi_upload' => FALSE,
        ];
        
        return view('portal.default.profile.index', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateContactRequest $request, ClientContact $client_contact)
    {

        $client_contact->fill($request->all());

        //update password if needed
        if($request->input('password'))
            $client_contact->password = Hash::make($request->input('password'));

        //update avatar if needed
        if($request->file('avatar')) 
        {
            $path = UploadAvatar::dispatchNow($request->file('avatar'), auth()->user()->client->client_hash);

            if($path)
            {
                $client_contact->avatar = $path;
                $client_contact->avatar_size = $request->file('avatar')->getSize();
                $client_contact->avatar_type = $request->file('avatar')->getClientOriginalExtension();
            }
        }

        $client_contact->save();

       // auth()->user()->fresh();

        return back();
    }

    public function settings()
    {
        return view('portal.default.settings.index');   
    }

    public function updateSettings(UpdateSettingsRequest $request)
    {

        return back();
    }
}
