<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal\Authentication;

use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Illuminate\Support\Str;
use App\Models\Subscription;
use App\Models\ClientContact;
use App\Factory\ClientFactory;
use App\Jobs\Mail\NinjaMailerJob;
use App\DataMapper\ClientSettings;
use App\Mail\Subscription\OtpCode;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Support\Facades\Cache;
use App\Repositories\ClientRepository;
use App\Repositories\ClientContactRepository;

class Register extends Component
{
    public Subscription $subscription;

    public array $context;

    public ?string $email;

    public ?string $password;

    public ?int $otp;

    public array $state = [
        'initial_completed' => false,
        'register_form' => false,
    ];

    public array $registration_fields = [];

    public function initial()
    {
        $this->validateOnly('email', ['email' => 'required|bail|email:rfc']);

        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription->company_id)
            ->first();

        if ($contact) {
            $this->addError('email', ctrans('texts.checkout_only_for_new_customers'));

            return;
        }

        $this->state['initial_completed'] = true;
        $this->state['register_form'] = true;
    }

    public function register(array $data)
    {
        $service = new ClientRegisterService(
            company: $this->subscription->company,
        );
        
        $rules = $service->rules(); 

        $data = Validator::make($data, $rules)->validate();

        $client = $service->createClient($data);
        $contact = $service->createClientContact($data, $client);

        auth()->guard('contact')->loginUsingId($contact->id, true);

        $this->dispatch('purchase.context', property: 'contact', value: $contact);
        $this->dispatch('purchase.next');
    }

    public function mount()
    {
        if (auth()->guard('contact')->check()) {
            $this->dispatch('purchase.context', property: 'contact', value: auth()->guard('contact')->user());
            $this->dispatch('purchase.next');
        }
    }

    public function render()
    {
        return view('billing-portal.v3.authentication.register');
    }
}
