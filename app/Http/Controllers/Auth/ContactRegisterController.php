<?php

namespace App\Http\Controllers\Auth;

use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\RegisterRequest;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ContactRegisterController extends Controller
{
    public function __construct()
    {
        $this->middleware(['guest', 'contact.register']);
    }

    public function showRegisterForm(string $company_key)
    {
        $company = Company::where('company_key', $company_key)->firstOrFail();

        return render('auth.register', compact(['company']));
    }

    public function register(RegisterRequest $request)
    {
        $request->merge(['company' => $request->company()]);

        $client = $this->getClient($request->all());
        $client_contact = $this->getClientContact($request->all(), $client);

        Auth::guard('contact')->login($client_contact, true);

        return redirect()->route('client.dashboard');
    }

    private function getClient(array $data)
    {
        $client = ClientFactory::create($data['company']->id, $data['company']->owner()->id);

        $client->fill($data);
        $client->save();

        return $client;
    }

    public function getClientContact(array $data, Client $client)
    {
        $client_contact = ClientContactFactory::create($data['company']->id, $data['company']->owner()->id);
        $client_contact->fill($data);

        $client_contact->client_id = $client->id;
        $client_contact->is_primary = true;
        $client_contact->password = Hash::make($data['password']);

        $client_contact->save();

        return $client_contact;
    }
}
