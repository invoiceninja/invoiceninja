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
        'otp' => true, // Use as preference. E-mail/password or OTP.
        'login_form' => false,
        'otp_form' => false,
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
            $this->addError('email', ctrans('texts.email_already_exists'));

            return;
        }

        $this->state['initial_completed'] = true;

        return $this->withOtp();
    }

    public function withOtp()
    {
        $code = rand(100000, 999999);
        $email_hash = "subscriptions:otp:{$this->email}";

        Cache::put($email_hash, $code, 600);

        $cc = new ClientContact();
        $cc->email = $this->email;

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new OtpCode($this->subscription->company, $this->context['contact'] ?? null, $code);
        $nmo->company = $this->subscription->company;
        $nmo->settings = $this->subscription->company->settings;
        $nmo->to_user = $cc;

        NinjaMailerJob::dispatch($nmo);

        if (app()->environment('local')) {
            session()->flash('message', "[dev]: Your OTP is: {$code}");
        }

        $this->state['otp_form'] = true;
    }

    public function handleOtp()
    {
        $this->validate([
            'otp' => 'required|numeric|digits:6',
        ]);

        $code = Cache::get("subscriptions:otp:{$this->email}");

        if ($this->otp != $code) { //loose comparison prevents edge cases
            $errors = $this->getErrorBag();
            $errors->add('otp', ctrans('texts.invalid_code'));

            return;
        }

        $this->state['otp_form'] = false;
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
