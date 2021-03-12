<?php

namespace App\Http\Livewire;

use App\Models\BillingSubscription;
use App\Models\ClientContact;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpClient\HttpClient;

class BillingPortalPurchase extends Component
{
    public $authenticated = false;

    public $email;

    public $password;

    public $billing_subscription;

    protected $rules = [
        'email' => ['required', 'email'],
    ];

    public $steps = [
        'passed_email' => false,
        'existing_user' => false,
        'fetched_payment_methods' => false,
    ];

    public $methods = [];

    public function authenticate()
    {
        $this->validate();

        // Search for existing e-mail (note on multiple databases).
        // If existing e-mail found, offer to login with password.
        // If not, create a new contact e-mail.

        $contact = ClientContact::where('email', $this->email)->first();

        if ($contact && $this->steps['existing_user'] === false) {
            return $this->steps['existing_user'] = true;
        }

        if ($contact && $this->steps['existing_user']) {
            $attempt = Auth::guard('contact')->attempt(['email' => $this->email, 'password' => $this->password]);

            if ($attempt) {
                return $this->getPaymentMethods($contact);
            } else {
                session()->flash('message', 'These credentials do not match our records.');
            }
        }

        $this->steps['existing_user'] = false;

        $this
            ->createBlankClient()
            ->getPaymentMethods();
    }

    protected function createBlankClient()
    {
        $company = Company::find($this->billing_subscription->company_id);

        $http_client = HttpClient::create();

//        $response = $http_client->request('GET', '/api/v1/contacts', [
//            'headers' => [
//                'X-Api-Token' => 'company-test-token',
//                'X-Requested-With' => 'XmlHttpRequest',
//            ],
//        ]);

//        dd($response->toArray());

        return $this;
    }

    protected function getPaymentMethods(ClientContact $contact): self
    {
        $this->steps['fetched_payment_methods'] = true;

        $this->methods = $contact->client->service()->getPaymentMethods(1000);

        return $this;
    }

    public function render()
    {
        return render('components.livewire.billing-portal-purchase');
    }
}
