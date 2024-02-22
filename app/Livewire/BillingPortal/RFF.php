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
            // 'contact_email' => ['sometimes', 'email'],
        ]);

        $this->contact = auth()->guard('contact')->user();
        $this->contact->first_name = $validated['contact_first_name'];
        $this->contact->last_name = $validated['contact_last_name'];
        $this->contact->save();

        $this->contact_first_name = $this->contact->first_name;
        $this->contact_last_name = $this->contact->last_name;
        $this->contact_email = $this->contact->email;

        $this->dispatch('purchase.context', property: 'contact.first_name', value: $this->contact->first_name);
        $this->dispatch('purchase.context', property: 'contact.last_name', value: $this->contact->last_name);

        $this->dispatch('purchase.next');
    }

    public function mount()
    {
        /** @var \App\Models\ClientContact $contact */
        $contact = auth()->guard('contact')->user();

        if ($contact->showRff() === false) {
            $this->dispatch('purchase.next');
        }
    }
    
    public function render()
    {
        return view('billing-portal.v3.rff');
    }
}
