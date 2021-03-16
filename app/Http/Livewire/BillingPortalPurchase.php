<?php

namespace App\Http\Livewire;

use App\Factory\ClientFactory;
use App\Models\ClientContact;
use App\Models\Invoice;
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

    public $invoice;

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
        $company = $this->billing_subscription->company;
        $user = User::first(); // TODO: What should be a value of $user?

        $client_repo = new ClientRepository(new ClientContactRepository());

        $client = $client_repo->save([
            'name' => 'Client Name',
            'contacts' => [
                ['email' => $this->email],
            ]
        ], ClientFactory::create($company->id, $user->id));

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

    public function handleMethodSelectingEvent($company_gateway_id, $gateway_type_id)
    {
        $this->company_gateway_id = $company_gateway_id;
        $this->payment_method_id = $gateway_type_id;

        $this->handleBeforePaymentEvents();
    }

    public function handleBeforePaymentEvents()
    {
        $company = $this->billing_subscription->company;
        $user = User::first(); // TODO: What should be a value of $user?

        $invoice = [
            'client_id' => $this->contact->client->id,
            'line_items' => [[
                'quantity' => 1,
                'cost' => 10,
                'product_key' => 'example',
                'notes' => 'example',
                'discount' => 0,
                'is_amount_discount' => true,
                'tax_rate1' => 0,
                'tax_rate2' => 0,
                'tax_rate3' => 0,
                'tax_name1' => '',
                'tax_name2' => '',
                'tax_name3' => '',
                'sort_id' => 0,
                'line_total' => 1,
                'custom_value1' => 'example',
                'custom_value2' => 'example',
                'custom_value3' => 'example',
                'custom_value4' => 'example',
                'type_id' => 1,
                'date' => '',
            ]],
        ];

        // TODO: Only for testing.
        $this->invoice = Invoice::where('status_id', Invoice::STATUS_SENT)->first();
//        $this->invoice = (new \App\Repositories\InvoiceRepository)->save($invoice, InvoiceFactory::create($company->id, $user->id));

        $this->emit('beforePaymentEventsCompleted');
    }

    public function render()
    {
        if ($this->contact instanceof ClientContact) {
            $this->getPaymentMethods($this->contact);
        }

        return render('components.livewire.billing-portal-purchase');
    }
}
