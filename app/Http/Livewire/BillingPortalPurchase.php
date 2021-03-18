<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Livewire;

use App\Factory\ClientFactory;
use App\Models\ClientContact;
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

    public $coupon;

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
        $user = $this->billing_subscription->user;

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
        $data = [
            'client_id' => $this->contact->client->id,
            'date' => now()->format('Y-m-d'),
            'invitations' => [[
                'key' => '',
                'client_contact_id' => $this->contact->hashed_id,
            ]],
            'user_input_promo_code' => $this->coupon,
            'quantity' => 1, // Option to increase quantity
        ];

        $this->invoice = $this->billing_subscription
            ->service()
            ->createInvoice($data)
            ->service()
            ->markSent()
            ->save();

        Cache::put($this->hash, [
            'email' => $this->email ?? $this->contact->email,
            'client_id' => $this->contact->client->id,
            'invoice_id' => $this->invoice->id],
            now()->addMinutes(60)
        );

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
