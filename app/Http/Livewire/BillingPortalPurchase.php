<?php

namespace App\Http\Livewire;

use App\Factory\ClientFactory;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class BillingPortalPurchase extends Component
{
    public $hash;

    public $heading_text = 'Log in';

    public $email;

    public $password;

    public $billing_subscription;

    public $contact;

    protected $rules = [
        'email' => ['required', 'email'],
    ];

    public $company_gateway_id;

    public $payment_method_id;

    public $steps = [
        'passed_email' => false,
        'existing_user' => false,
        'fetched_payment_methods' => false,
        'fetched_client' => false,
    ];

    public $methods = [];

    public function authenticate()
    {
        $this->validate();

        $contact = ClientContact::where('email', $this->email)->first();

        if ($contact && $this->steps['existing_user'] === false) {
            return $this->steps['existing_user'] = true;
        }

        if ($contact && $this->steps['existing_user']) {
            $attempt = Auth::guard('contact')->attempt(['email' => $this->email, 'password' => $this->password]);

            return $attempt
                ? $this->getPaymentMethods($contact)
                : session()->flash('message', 'These credentials do not match our records.');
        }

        $this->steps['existing_user'] = false;

        $contact = $this->createBlankClient();

        if ($contact && $contact instanceof ClientContact) {
            $this->getPaymentMethods($contact);
        }
    }

    protected function createBlankClient()
    {
        $company = Company::first();
        $user = User::first();

        $client_repo = new ClientRepository(new ClientContactRepository());
        $client_data = [
            'name' => 'Client Name',
            'contacts' => [
                ['email' => $this->email],
            ]
        ];

        $client = $client_repo->save($client_data, ClientFactory::create($company->id, $user->id));

        return $client->contacts->first();
    }

    protected function getPaymentMethods(ClientContact $contact): self
    {
        Cache::put($this->hash, ['email' => $this->email ?? $this->contact->email, 'url' => url()->current()]);

        $this->steps['fetched_payment_methods'] = true;

        $this->methods = $contact->client->service()->getPaymentMethods(1000);

        $this->heading_text = 'Pick a payment method';

        Auth::guard('contact')->login($contact);

        $this->contact = $contact;

        return $this;
    }

    public function render()
    {
        if ($this->contact instanceof ClientContact) {
            $this->getPaymentMethods($this->contact);
        }

        return render('components.livewire.billing-portal-purchase');
    }
}
