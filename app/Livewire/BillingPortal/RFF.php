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

use App\Models\ClientContact;
use Livewire\Component;

class RFF extends Component
{
    public array $context;

    public ClientContact $contact;

    public ?string $contact_first_name;
    public ?string $contact_last_name;
    public ?string $contact_email;

    public function handleRff()
    {
        $validated = $this->validate([
            'contact_first_name' => ['required'],
            'contact_last_name' => ['required'],
            'contact_email' => ['required', 'email'],
        ]);

        $this->contact->first_name = $validated['contact_first_name'];
        $this->contact->last_name = $validated['contact_last_name'];
        $this->contact->email = $validated['contact_email'];
        $this->contact->save();

        $this->dispatch('purchase.next');
    }

    public function mount()
    {
        if (auth()->guard('contact')->user()->showRff() === false) {
            $this->dispatch('purchase.next');
        }

        $this->contact = auth()->guard('contact')->user();

        $this->contact_first_name = $this->contact->first_name;
        $this->contact_last_name = $this->contact->last_name;
        $this->contact_email = $this->contact->email;
    }

    public function render()
    {
        return view('billing-portal.v3.rff');
    }
}
