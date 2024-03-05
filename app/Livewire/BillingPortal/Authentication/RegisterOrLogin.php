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

class RegisterOrLogin extends Component
{
    public Subscription $subscription;

    public array $context;

    public ?string $email;

    public ?string $password;

    public ?int $otp;

    public array $state = [
        'otp' => false, // Use as preference. E-mail/password or OTP.
        'login_form' => false,
        'otp_form' => false,
        'register_form' => false,
        'initial_completed' => false,
    ];

    public array $registration_fields = [];

    public function initial()
    {
        $this->validateOnly('email', ['email' => 'required|bail|email:rfc']);

        $this->state['initial_completed'] = true;

        if ($this->state['otp']) {
            return $this->withOtp();
        }

        return $this->withPassword();
    }

    public function withPassword()
    {
        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription->company_id)
            ->first();

        if ($contact) {
            return $this->state['login_form'] = true;
        }

        $this->state['login_form'] = false;
        $this->state['register_form'] = true;
    }

    public function handlePassword()
    {
        $this->validate([
            'email' => 'required|bail|email:rfc',
            'password' => 'required',
        ]);

        $attempt = auth()->guard('contact')->attempt([
            'email' => $this->email,
            'password' => $this->password,
            'company_id' => $this->subscription->company_id,
        ]);

        if ($attempt) {
            $this->dispatch('purchase.next');
        }

        session()->flash('message', 'These credentials do not match our records.');
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

        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription->company_id)
            ->first();

        if ($contact) {
            auth()->guard('contact')->loginUsingId($contact->id, true);

            $this->dispatch('purchase.context', property: 'contact', value: $contact);
            $this->dispatch('purchase.next');

            return;
        }

        $this->state['otp_form'] = false;
        $this->state['register_form'] = true;
    }

    private function createClientContact(array $data)
    {
        $fields = [
            'client' => collect($data)->filter(fn($value, $key) => str_starts_with($key, 'client_'))->mapWithKeys(fn($value, $key) => [str_replace('client_', '', $key) => $value])->toArray(),
            'contact' => collect($data)->filter(fn($value, $key) => str_starts_with($key, 'contact_'))->mapWithKeys(fn($value, $key) => [str_replace('contact_', '', $key) => $value])->toArray(),
        ];

        $company = $this->subscription->company;
        $user = $this->subscription->user;
        $user->setCompany($company);

        $client_repo = new ClientRepository(new ClientContactRepository());
        $data = [
            'name' => '',
            'group_settings_id' => $this->subscription->group_id,
            'contacts' => [
                ['email' => $this->email, ...$fields['contact']],
            ],
            'client_hash' => Str::random(40),
            'settings' => ClientSettings::defaults(),
            ...$fields['client'],
        ];

        $client = $client_repo->save($data, ClientFactory::create($company->id, $user->id));

        $contact = $client->fresh()->contacts()->first();

        return $contact;
    }

    public function register(array $data)
    {
        
        $rules = collect($this->registrationFields() ?? [])->mapWithKeys(function ($field, $value) {
            return [$field['key'] => $field['required'] ? ['required'] : []];
        })->toArray();
        
        $data = Validator::make($data, $rules)->validate();

        $contact = $this->createClientContact($data);

        auth()->guard('contact')->loginUsingId($contact->id, true);

        $this->dispatch('purchase.context', property: 'contact', value: $contact);
        $this->dispatch('purchase.next');
    }

    public function registrationFields()
    {
        $contact = ['first_name', 'last_name', 'email'];

        $defaults = [
            'contact_email' => $this->email,
        ];

        return collect($this->subscription->company->client_registration_fields)
            ->filter(fn($field) => $field['visible'] || $field['required'])
            ->map(function ($field) use ($contact) {
                return [
                    'key' => in_array($field['key'], $contact) ? "contact_{$field['key']}" : "client_{$field['key']}",
                    'label' => ctrans("texts.{$field['key']}"),
                    'defaultValue' => null,
                    'required' => $field['required'],
                    'type' => str_ends_with($field['key'], 'email')
                        ? 'email' : (str_ends_with($field['key'], 'phone')
                            ? 'tel' : (str_ends_with($field['key'], 'password')
                                ? 'password'
                                : 'text')
                        ),
                ];
            })
            ->mapWithKeys(fn($field) => [
                $field['key'] => [
                    ...$field,
                    'defaultValue' => $defaults[$field['key']] ?? null,
                ]
            ])
            ->toArray();
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
        return view('billing-portal.v3.authentication.register-or-login');
    }
}
