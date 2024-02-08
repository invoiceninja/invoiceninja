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

namespace App\Livewire\BillingPortal;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Subscription\OtpCode;
use App\Models\ClientContact;
use App\Models\Subscription;
use Livewire\Component;

class Authentication extends Component
{
    public Subscription $subscription;

    public string $email;

    public array $state = [
        'code' => false,
    ];

    public function authenticate()
    {
        $this->validateOnly('email', ['email' => 'required|bail|email:rfc']);

        $code = rand(100000, 999999);
        $hash = sprintf('subscriptions:otp:%s', $code);

        cache()->put($hash, $code, ttl: 120);

        $cc = new ClientContact();
        $cc->email = $this->email;

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new OtpCode($this->subscription->company, $this->contact, $code);
        $nmo->company = $this->subscription->company;
        $nmo->settings = $this->subscription->company->settings;
        $nmo->to_user = $cc;
        
        NinjaMailerJob::dispatch($nmo);

        $this->state['code'] = true;
    }

    public function mount()
    {
        if (auth()->guard('contact')->check()) {
            $this->dispatch('purchase.next');
        }
    }

    public function render()
    {
        return view('billing-portal.v3.authentication');
    }
}
