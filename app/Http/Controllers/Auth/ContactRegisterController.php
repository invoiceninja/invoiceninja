<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\RegisterRequest;
use App\Models\Client;
use App\Models\ClientContact;
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
        return render('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        if ($request->subdomain) {
            $company = Company::where('subdomain', $request->subdomain)->firstOrFail();
        }

        if ($request->company_key) {
            $company = Company::where('company_key', $request->company_key)->firstOrFail();
        }

        $client = factory(Client::class)->create([
            'user_id' => $company->owner()->id,
            'company_id' => $company->id
        ]);

        $client_contact = ClientContact::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'company_id' => $company->id,
            'password' => Hash::make($request->password),
            'client_id' => $client->id,
            'user_id' => $company->owner()->id,
            'is_primary' => true,
            'contact_key' => \Illuminate\Support\Str::random(40),
        ]);

        Auth::guard('contact')->login($client_contact, true);

        return redirect()->route('client.dashboard');
    }
}
